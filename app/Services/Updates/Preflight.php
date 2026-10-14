<?php

namespace App\Services\Updates;

use App\Support\AppVersion;
use App\Support\Semver;

/**
 * Pre-flight check: dacă ORICE verificare critică eșuează, actualizarea NU se instalează.
 */
class Preflight
{
    public function __construct(protected ApplicationRuntime $runtime) {}

    /**
     * @return list<array{check:string, ok:bool, critical:bool, message:string}>
     */
    public function run(Manifest $manifest, string $targetRoot): array
    {
        $results = [];
        $add = function (string $check, bool $ok, string $message, bool $critical = true) use (&$results) {
            $results[] = compact('check', 'ok', 'critical', 'message');
        };

        $phpReq = (string) ($manifest->requires['php'] ?? '>=8.3');
        $add('php', Semver::satisfies(PHP_VERSION, $phpReq), 'PHP '.PHP_VERSION." (necesar {$phpReq})");

        foreach (['zip', 'pdo', 'mbstring', 'openssl', 'json', 'fileinfo'] as $ext) {
            $add("ext:{$ext}", extension_loaded($ext), "Extensia {$ext}");
        }

        $free = @disk_free_space($targetRoot);
        $minMb = (int) config('update.min_free_disk_mb', 200);
        if ($free === false) {
            $add('disk', true, 'Spațiul liber nu poate fi determinat pe acest hosting (verificare omisă).', false);
        } else {
            $add('disk', $free >= $minMb * 1024 * 1024, 'Spațiu liber: '.round($free / 1048576).' MB (minim '.$minMb.' MB)');
        }

        // Permisiuni de scriere pe fiecare director țintă (top-level) din pachet
        $dirs = [];
        foreach (array_merge(array_keys($manifest->files), $manifest->delete) as $rel) {
            $parts = explode('/', $rel);
            $dirs[count($parts) > 1 ? $parts[0] : '.'] = true;
        }
        foreach (array_keys($dirs) as $d) {
            $path = $d === '.' ? $targetRoot : $targetRoot.'/'.$d;
            $ok = is_dir($path) ? is_writable($path) : is_writable($targetRoot);
            $add("write:{$d}", $ok, "Permisiune de scriere: {$d}/");
        }

        $add('database', $this->runtime->checkDatabase(), 'Conexiune bază de date');
        $add('storage', is_writable(storage_path()) && is_writable(storage_path('framework')), 'storage/ și storage/framework/ inscriptibile');

        $backupRoot = BackupService::root();
        if (! is_dir($backupRoot)) {
            @mkdir($backupRoot, 0750, true);
        }
        $add('backup_dir', is_dir($backupRoot) && is_writable($backupRoot), 'Director backup: storage/app/private/backups/updates');

        $current = AppVersion::current($targetRoot);
        $add('version', Semver::gte($current, $manifest->minimumVersion) && Semver::gt($manifest->version, $current),
            "Compatibilitate versiune: {$current} → {$manifest->version} (minim {$manifest->minimumVersion})");

        return $results;
    }

    public static function passed(array $results): bool
    {
        foreach ($results as $r) {
            if ($r['critical'] && ! $r['ok']) {
                return false;
            }
        }

        return true;
    }
}
