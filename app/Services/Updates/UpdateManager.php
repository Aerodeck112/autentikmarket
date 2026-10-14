<?php

namespace App\Services\Updates;

use App\Enums\UpdateStatus;
use App\Models\ApplicationUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AppVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * UPDATE MANAGER — actualizarea aplicației prin pachete ZIP încărcate din CRM.
 *
 * Fluxul (fiecare pas = request HTTP separat / proces PHP nou):
 *   upload+validare → preflight → backup → maintenance → install → migrate → optimize → verify → finalize
 *
 * Reguli de siguranță:
 *  - nu se execută niciun cod din pachet (fără eval/exec/hook-uri); doar fișiere + `migrate --force`;
 *  - .env, storage/, upload-uri, log-uri și backup-uri nu sunt niciodată atinse;
 *  - backup obligatoriu înainte de install; rollback automat al fișierelor la eșec;
 *  - baza de date NU este restaurată automat (risc de pierdere date) — dump-ul rămâne disponibil.
 */
class UpdateManager
{
    public const STEPS = ['preflight', 'backup', 'maintenance', 'install', 'migrate', 'optimize', 'verify', 'finalize'];

    public function __construct(
        protected ApplicationRuntime $runtime,
        protected BackupService $backups,
    ) {}

    public function targetRoot(): string
    {
        return rtrim((string) config('update.target_path', base_path()), '/');
    }

    public static function packagesDir(): string
    {
        return storage_path('app/private/updates/packages');
    }

    /**
     * Pasul 1–5: încarcă pachetul (copiat în storage privat) și îl validează.
     */
    public function receive(string $sourcePath, string $originalName, ?User $user = null): ApplicationUpdate
    {
        $update = ApplicationUpdate::create([
            'status' => UpdateStatus::Uploaded,
            'original_name' => mb_substr(basename($originalName), 0, 255),
            'previous_version' => AppVersion::current($this->targetRoot()),
            'uploaded_by' => $user?->id,
        ]);

        try {
            if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
                throw new UpdateException('Extensie invalidă: este acceptat doar .zip.');
            }
            File::ensureDirectoryExists(self::packagesDir(), 0750);
            $stored = self::packagesDir().'/'.$update->uuid.'.zip';
            if (! @copy($sourcePath, $stored)) {
                throw new UpdateException('Pachetul nu a putut fi salvat în storage.');
            }
            $update->forceFill([
                'package_path' => $stored,
                'package_sha256' => hash_file('sha256', $stored),
                'package_size' => filesize($stored),
            ]);
            $update->appendLog('upload', 'Pachet încărcat: '.$update->original_name.' ('.round($update->package_size / 1024).' KB)');

            $result = PackageValidator::make()->validate($stored, AppVersion::current($this->targetRoot()));
            $m = $result['manifest'];

            $update->forceFill([
                'status' => UpdateStatus::Validated,
                'version' => $m->version,
                'manifest' => $m->raw,
                'release_notes' => trim($result['release_notes']."\n\n".$result['release_notes_hu']),
                'signature_valid' => $result['signature_valid'],
                'destructive_migrations' => $result['destructive'] ? ['files' => $result['destructive'], 'confirmed_by' => null] : null,
            ]);
            $update->appendLog('validate', "Pachet valid: Autentik Market {$m->version} (necesită ≥ {$m->minimumVersion}), ".count($m->files).' fișiere, '.count($m->delete).' ștergeri.');
            $update->save();
        } catch (Throwable $e) {
            $update->forceFill(['status' => UpdateStatus::Rejected, 'message' => $e->getMessage()]);
            $update->appendLog('validate', $e->getMessage(), 'error');
            $update->save();
            if ($update->package_path && is_file($update->package_path)) {
                @unlink($update->package_path);
            }
            AuditLogger::log('update.rejected', $update, [], ['reason' => $e->getMessage()], $user?->id);
        }

        return $update->fresh();
    }

    public function confirmDestructive(ApplicationUpdate $update, User $user): void
    {
        $d = $update->destructive_migrations;
        if ($d) {
            $d['confirmed_by'] = $user->id;
            $d['confirmed_at'] = now()->toIso8601String();
            $update->destructive_migrations = $d;
            $update->appendLog('confirm', 'Migrațiile marcate ca potențial distructive au fost confirmate de '.$user->name.'.', 'warning');
            $update->save();
            AuditLogger::log('update.destructive_confirmed', $update, [], ['files' => array_column($d['files'], 'file')], $user->id);
        }
    }

    public function nextStep(ApplicationUpdate $update): ?string
    {
        if (in_array($update->status, [UpdateStatus::Completed, UpdateStatus::Failed, UpdateStatus::RolledBack, UpdateStatus::Rejected], true)) {
            return null;
        }
        $idx = $update->current_step ? array_search($update->current_step, self::STEPS, true) : -1;

        return self::STEPS[$idx + 1] ?? null;
    }

    /**
     * Rulează UN pas. Pașii trebuie rulați strict în ordine.
     *
     * @return array{ok:bool, step:string, next:?string, message:string, data?:array}
     */
    public function runStep(ApplicationUpdate $update, string $step, ?User $user = null): array
    {
        $expected = $this->nextStep($update);
        if ($expected === null || $step !== $expected) {
            return ['ok' => false, 'step' => $step, 'next' => $expected, 'message' => "Pas neașteptat „{$step}” (așteptat: ".($expected ?? '—').').'];
        }
        if ($step === 'preflight' && $this->anotherUpdateRunning($update)) {
            return ['ok' => false, 'step' => $step, 'next' => $expected, 'message' => 'O altă actualizare este în curs.'];
        }

        $manifest = Manifest::fromJson(json_encode($update->manifest));
        $started = microtime(true);

        try {
            $data = match ($step) {
                'preflight' => $this->stepPreflight($update, $manifest, $user),
                'backup' => $this->stepBackup($update, $manifest),
                'maintenance' => $this->stepMaintenance($update),
                'install' => $this->stepInstall($update, $manifest),
                'migrate' => $this->stepMigrate($update, $manifest),
                'optimize' => $this->stepOptimize($update),
                'verify' => $this->stepVerify($update, $manifest),
                'finalize' => $this->stepFinalize($update, $manifest, $user),
            };
            $update->current_step = $step;
            $update->appendLog($step, ($data['message'] ?? 'OK').' ('.round((microtime(true) - $started) * 1000).' ms)');
            $update->save();

            return ['ok' => true, 'step' => $step, 'next' => $this->nextStep($update), 'message' => $data['message'] ?? 'OK', 'data' => $data];
        } catch (Throwable $e) {
            return $this->fail($update, $step, $e, $user);
        }
    }

    /** Rulează toți pașii rămași (CLI). */
    public function runAll(ApplicationUpdate $update, ?User $user = null, ?callable $progress = null): array
    {
        $last = ['ok' => false, 'message' => 'Nimic de rulat.'];
        while ($step = $this->nextStep($update)) {
            $last = $this->runStep($update, $step, $user);
            $progress && $progress($last);
            $update->refresh();
            if (! $last['ok']) {
                break;
            }
        }

        return $last;
    }

    // ---- Pași -------------------------------------------------------------

    protected function stepPreflight(ApplicationUpdate $update, Manifest $manifest, ?User $user): array
    {
        if (! $update->package_path || ! is_file($update->package_path)
            || ! hash_equals((string) $update->package_sha256, hash_file('sha256', $update->package_path))) {
            throw new UpdateException('Pachetul stocat lipsește sau a fost modificat după validare.');
        }
        $d = $update->destructive_migrations;
        if ($d && empty($d['confirmed_by'])) {
            throw new UpdateException('Pachetul conține migrații potențial distructive care necesită confirmare explicită.');
        }

        $results = (new Preflight($this->runtime))->run($manifest, $this->targetRoot());
        $update->forceFill(['started_at' => now(), 'installed_by' => $user?->id]);
        if (! Preflight::passed($results)) {
            $failed = array_filter($results, fn ($r) => $r['critical'] && ! $r['ok']);
            throw new UpdateException('Pre-flight check eșuat: '.implode('; ', array_column($failed, 'message')));
        }

        return ['message' => 'Pre-flight check reușit ('.count($results).' verificări).', 'checks' => $results];
    }

    protected function stepBackup(ApplicationUpdate $update, Manifest $manifest): array
    {
        $backup = $this->backups->create($update, $manifest, $this->targetRoot());
        $update->status = UpdateStatus::BackupCreated;

        return ['message' => 'Backup creat'.($backup->database_included ? ' (fișiere + bază de date)' : ' (fișiere)').'.'];
    }

    protected function stepMaintenance(ApplicationUpdate $update): array
    {
        $update->status = UpdateStatus::Installing;
        $this->runtime->down();

        return ['message' => 'Aplicația a intrat în maintenance mode.'];
    }

    protected function stepInstall(ApplicationUpdate $update, Manifest $manifest): array
    {
        $guard = PathGuard::fromConfig();
        $root = $this->targetRoot();
        $zip = new ZipArchive;
        if ($zip->open($update->package_path, ZipArchive::RDONLY) !== true) {
            throw new UpdateException('Pachetul nu poate fi deschis.');
        }
        $written = 0;
        try {
            // VERSION se scrie ULTIMUL — versiunea se schimbă doar după ce restul fișierelor sunt la locul lor.
            $paths = array_keys($manifest->files);
            usort($paths, fn ($a, $b) => ($a === 'VERSION') <=> ($b === 'VERSION'));
            foreach ($paths as $rel) {
                $target = $guard->resolveInside($root, $rel);
                FileWriter::writeStream($target, $zip->getStream('files/'.$rel));
                if (! hash_equals($manifest->files[$rel], (string) hash_file('sha256', $target))) {
                    throw new UpdateException("Verificarea după scriere a eșuat pentru {$rel}.");
                }
                $written++;
            }
        } finally {
            $zip->close();
        }

        $deleted = 0;
        foreach ($manifest->delete as $rel) {
            $target = $guard->resolveInside($root, $rel);
            if (is_file($target) && @unlink($target)) {
                $deleted++;
            }
        }
        $this->runtime->clearCaches();

        return ['message' => "Fișiere instalate: {$written}, șterse: {$deleted}."];
    }

    protected function stepMigrate(ApplicationUpdate $update, Manifest $manifest): array
    {
        $output = $this->runtime->migrate();

        return ['message' => $manifest->migrationRequired ? 'Migrațiile au rulat.' : 'Verificare migrații finalizată.', 'output' => mb_substr($output, 0, 4000)];
    }

    protected function stepOptimize(ApplicationUpdate $update): array
    {
        $this->runtime->clearCaches();
        $this->runtime->optimize();

        return ['message' => 'Cache curățat și reconstruit.'];
    }

    protected function stepVerify(ApplicationUpdate $update, Manifest $manifest): array
    {
        $root = $this->targetRoot();
        if (AppVersion::current($root) !== $manifest->version) {
            throw new UpdateException('Fișierul VERSION nu indică noua versiune după instalare.');
        }
        foreach ($manifest->files as $rel => $hash) {
            if (! hash_equals($hash, (string) @hash_file('sha256', $root.'/'.$rel))) {
                throw new UpdateException("Verificare integritate eșuată: {$rel}");
            }
        }
        if (! $this->runtime->checkDatabase()) {
            throw new UpdateException('Baza de date nu răspunde după actualizare.');
        }
        $pending = $this->runtime->pendingMigrations();
        if ($pending !== []) {
            throw new UpdateException('Au rămas migrații nerulate: '.implode(', ', array_slice($pending, 0, 5)));
        }

        return ['message' => 'Test de bază reușit: versiune, integritate fișiere, bază de date, migrații.'];
    }

    protected function stepFinalize(ApplicationUpdate $update, Manifest $manifest, ?User $user): array
    {
        $this->runtime->up();
        $update->forceFill([
            'status' => UpdateStatus::Completed,
            'finished_at' => now(),
            'duration_ms' => $update->started_at ? (int) $update->started_at->diffInMilliseconds(now()) : null,
            'message' => 'Actualizarea a fost instalată cu succes.',
        ]);
        AuditLogger::log('update.installed', $update, ['version' => $update->previous_version], ['version' => $manifest->version], $user?->id);

        return ['message' => "Autentik Market {$manifest->version} este online."];
    }

    // ---- Eșec + rollback ----------------------------------------------------

    protected function fail(ApplicationUpdate $update, string $step, Throwable $e, ?User $user): array
    {
        $update->appendLog($step, 'EȘEC: '.$e->getMessage(), 'error');
        $update->forceFill([
            'status' => UpdateStatus::Failed,
            'message' => "Pasul „{$step}” a eșuat: ".$e->getMessage(),
            'finished_at' => now(),
            'duration_ms' => $update->started_at ? (int) $update->started_at->diffInMilliseconds(now()) : null,
        ])->save();
        AuditLogger::log('update.failed', $update, [], ['step' => $step, 'error' => $e->getMessage()], $user?->id);

        $filesTouched = in_array($step, ['install', 'migrate', 'optimize', 'verify', 'finalize'], true);
        $rollbackMsg = '';
        if ($filesTouched) {
            $rollbackMsg = $this->rollback($update, $user) ? ' Fișierele anterioare au fost restaurate.' : ' Restaurarea automată a eșuat — folosiți backup-ul manual.';
        }

        try {
            if ($this->runtime->isDown()) {
                $this->runtime->up();
            }
        } catch (Throwable) {
        }

        return ['ok' => false, 'step' => $step, 'next' => null, 'message' => $update->fresh()->message.$rollbackMsg];
    }

    public function rollback(ApplicationUpdate $update, ?User $user = null): bool
    {
        $backup = $update->backup;
        if (! $backup || ! $backup->files_archive || ! is_file($backup->files_archive)) {
            $update->appendLog('rollback', 'Nu există backup de fișiere pentru rollback.', 'error');
            $update->save();

            return false;
        }

        try {
            $restored = $this->backups->restoreFiles($backup, $this->targetRoot());
            $this->runtime->clearCaches();
            $update->appendLog('rollback', "Rollback fișiere reușit ({$restored} fișiere restaurate). Baza de date NU a fost modificată de rollback (siguranța datelor).", 'warning');
            $update->forceFill(['status' => UpdateStatus::RolledBack])->save();
            AuditLogger::log('update.rollback', $update, [], ['restored' => $restored, 'version' => $backup->version], $user?->id);

            return true;
        } catch (Throwable $e) {
            $update->appendLog('rollback', 'Rollback eșuat: '.$e->getMessage(), 'error');
            $update->save();
            AuditLogger::log('update.rollback_failed', $update, [], ['error' => $e->getMessage()], $user?->id);

            return false;
        } finally {
            try {
                if ($this->runtime->isDown()) {
                    $this->runtime->up();
                }
            } catch (Throwable) {
            }
        }
    }

    protected function anotherUpdateRunning(ApplicationUpdate $update): bool
    {
        return ApplicationUpdate::where('id', '!=', $update->id)
            ->whereIn('status', [UpdateStatus::BackupCreated->value, UpdateStatus::Installing->value])
            ->where('updated_at', '>', now()->subHour())
            ->exists();
    }

    public static function newUuid(): string
    {
        return (string) Str::uuid();
    }
}
