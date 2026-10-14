<?php

namespace App\Services\Updates;

use App\Models\ApplicationUpdate;
use App\Models\UpdateBackup;
use App\Support\AppVersion;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Backup automat ÎNAINTE de orice update:
 *  - fișierele aplicației afectate de update (cele suprascrise și cele șterse);
 *  - lista fișierelor noi (pentru rollback);
 *  - dump-ul bazei de date (dacă hostingul permite);
 *  - informațiile despre versiune.
 * Locație: storage/app/private/backups/updates/… (NU este public).
 */
class BackupService
{
    public function __construct(protected DatabaseDumper $dumper) {}

    public static function root(): string
    {
        return storage_path('app/private/backups/updates');
    }

    public function create(ApplicationUpdate $update, Manifest $manifest, string $targetRoot): UpdateBackup
    {
        $current = AppVersion::current($targetRoot);
        $dir = self::root().'/'.now()->format('Ymd-His').'-v'.$current.'-to-v'.$manifest->version.'-'.substr($update->uuid, 0, 8);
        File::ensureDirectoryExists($dir, 0750);
        $this->protectDirectory(self::root());

        $guard = PathGuard::fromConfig();
        $zipPath = $dir.'/files.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new UpdateException('Nu pot crea arhiva de backup a fișierelor.');
        }

        $newFiles = [];
        $backedUp = 0;
        foreach (array_merge(array_keys($manifest->files), $manifest->delete) as $rel) {
            $guard->assertWritable($rel);
            $abs = rtrim($targetRoot, '/').'/'.$rel;
            if (is_file($abs) && ! is_link($abs)) {
                if (! $zip->addFile($abs, $rel)) {
                    $zip->close();
                    throw new UpdateException("Nu pot adăuga în backup: {$rel}");
                }
                $backedUp++;
            } elseif (! file_exists($abs) && array_key_exists($rel, $manifest->files)) {
                $newFiles[] = $rel;
            }
        }
        $zip->addFromString('.backup-info.json', json_encode([
            'version' => $current,
            'target_version' => $manifest->version,
            'created_at' => now()->toIso8601String(),
            'files' => $backedUp,
        ], JSON_PRETTY_PRINT));
        if (! $zip->close()) {
            throw new UpdateException('Arhiva de backup nu a putut fi finalizată.');
        }

        file_put_contents($dir.'/version.json', json_encode([
            'version' => $current,
            'target_version' => $manifest->version,
            'update_uuid' => $update->uuid,
            'php' => PHP_VERSION,
            'created_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));

        $dbPath = null;
        $dbIncluded = false;
        if (config('update.backup_database', true)) {
            try {
                $dbPath = $dir.'/database.sql.gz';
                $this->dumper->dump($dbPath);
                $dbIncluded = true;
            } catch (\Throwable $e) {
                $update->appendLog('backup', 'Backup DB indisponibil: '.$e->getMessage(), 'warning');
                $dbPath = null;
            }
        }

        $size = 0;
        foreach (File::files($dir) as $f) {
            $size += $f->getSize();
        }

        $backup = UpdateBackup::create([
            'application_update_id' => $update->id,
            'version' => $current,
            'directory' => $dir,
            'files_archive' => $zipPath,
            'database_dump' => $dbPath,
            'database_included' => $dbIncluded,
            'new_files' => $newFiles,
            'size_bytes' => $size,
        ]);

        $this->prune();

        return $backup;
    }

    /** Restaurează fișierele dintr-un backup (rollback). Nu atinge baza de date. */
    public function restoreFiles(UpdateBackup $backup, string $targetRoot): int
    {
        $guard = PathGuard::fromConfig();
        $zip = new ZipArchive;
        if ($zip->open($backup->files_archive, ZipArchive::RDONLY) !== true) {
            throw new UpdateException('Arhiva de backup nu poate fi deschisă.');
        }
        $restored = 0;
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === '.backup-info.json' || str_ends_with($name, '/')) {
                    continue;
                }
                $target = $guard->resolveInside($targetRoot, $name);
                $stream = $zip->getStream($name);
                FileWriter::writeStream($target, $stream);
                $restored++;
            }
        } finally {
            $zip->close();
        }

        foreach ($backup->new_files ?? [] as $rel) {
            $target = $guard->resolveInside($targetRoot, $rel);
            if (is_file($target)) {
                @unlink($target);
            }
        }

        return $restored;
    }

    public function prune(): void
    {
        $keep = max(1, (int) config('update.keep_backups', 5));
        $old = UpdateBackup::orderByDesc('id')->skip($keep)->take(100)->get();
        foreach ($old as $b) {
            if ($b->directory && str_starts_with($b->directory, self::root()) && is_dir($b->directory)) {
                File::deleteDirectory($b->directory);
            }
            $b->update(['files_archive' => null, 'database_dump' => null]);
        }
    }

    /** .htaccess de siguranță (deny) — chiar dacă storage ar fi expus din greșeală. */
    protected function protectDirectory(string $dir): void
    {
        $ht = $dir.'/.htaccess';
        if (! is_file($ht)) {
            @file_put_contents($ht, "Require all denied\nDeny from all\n");
        }
    }
}
