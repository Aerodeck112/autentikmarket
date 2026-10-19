<?php

namespace App\Services\Updates;

use App\Support\Semver;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use ZipArchive;

/**
 * Generator de pachete (rulat pe mașina de DEZVOLTARE, nu pe server):
 *   php artisan release:build 1.4.0            → releases/dist/autentikmarket-update-1.4.0.zip
 *   php artisan release:build 1.0.0 --install  → releases/dist/autentikmarket-install-1.0.0.zip (instalare inițială completă)
 * Inventarul fiecărei versiuni este salvat în releases/manifests/{versiune}.json (comis în git),
 * pentru a calcula automat fișierele de șters la următorul update.
 */
class ReleaseBuilder
{
    /** Căi incluse în pachetele de update (cod aplicație). */
    public const APP_PATHS = [
        'app', 'bootstrap/app.php', 'bootstrap/providers.php', 'config',
        'database/migrations', 'database/seeders', 'database/factories',
        'lang', 'public/build', 'public/images', 'public/icons',
        'public/index.php', 'public/.htaccess', 'public/favicon.ico', 'public/favicon.svg',
        'public/manifest.webmanifest', 'public/sw.js',
        'resources/views', 'resources/pdf', 'routes', 'artisan', 'composer.json', 'composer.lock', 'VERSION', 'docs',
    ];

    /** Căi suplimentare pentru pachetul de INSTALARE inițială. */
    public const INSTALL_EXTRA = [
        // Instalatorul web: doar în pachetul de instalare. Pachetele de update nu pot conține PHP în public/
        // (PathGuard) — după instalare oricum se blochează singur.
        'public/install.php',
        '.env.example', '.htaccess', 'storage', 'bootstrap/cache/.gitignore', 'README.md',
        'resources/css', 'resources/js', 'package.json', 'package-lock.json', 'vite.config.js', 'phpunit.xml', 'tests',
    ];

    public function __construct(protected string $root) {}

    public function manifestsDir(): string
    {
        return $this->root.'/releases/manifests';
    }

    public function distDir(): string
    {
        return $this->root.'/releases/dist';
    }

    /**
     * @return array{path:string, manifest:array, files:int, deleted:int, vendor:bool}
     */
    public function buildUpdate(string $version, ?string $from = null, ?bool $withVendor = null, ?string $signingKey = null, array $options = []): array
    {
        $this->assertBuildable($version);
        $from ??= $this->latestInventoryVersion($version);
        if (! $from) {
            throw new UpdateException('Nu există inventarul versiunii anterioare (releases/manifests/*.json). Folosiți --from sau construiți întâi pachetul de instalare.');
        }
        $previous = $this->loadInventory($from);

        $files = $this->collect(self::APP_PATHS);
        $lockHash = isset($files['composer.lock']) ? hash_file('sha256', $this->root.'/composer.lock') : null;
        $prevLockHash = $previous['files']['composer.lock'] ?? null;
        $includeVendor = $withVendor ?? ($lockHash !== $prevLockHash);
        if ($includeVendor) {
            $files += $this->collect(['vendor']);
        }

        $hashes = [];
        foreach ($files as $rel => $abs) {
            $hashes[$rel] = hash_file('sha256', $abs);
        }
        ksort($hashes, SORT_STRING);

        // Fișiere de șters = existau în versiunea anterioară (în aceleași categorii) și nu mai există acum.
        $delete = [];
        foreach (array_keys($previous['files'] ?? []) as $rel) {
            if (isset($hashes[$rel])) {
                continue;
            }
            $isVendor = str_starts_with($rel, 'vendor/');
            if ($isVendor && ! $includeVendor) {
                continue;
            }
            if (! $isVendor && ! $this->inPaths($rel, self::APP_PATHS)) {
                continue;
            }
            $delete[] = $rel;
        }
        sort($delete, SORT_STRING);

        $migrationsChanged = (bool) array_filter(array_keys($hashes), fn ($p) => str_starts_with($p, 'database/migrations/')
            && (($previous['files'][$p] ?? null) !== $hashes[$p]));

        $manifest = [
            'format' => 1,
            'application' => config('update.application_id'),
            'version' => $version,
            'minimum_version' => $options['minimum_version'] ?? $from,
            'release_date' => now()->toDateString(),
            'requires' => ['php' => '>=8.3', 'extensions' => ['pdo_mysql', 'zip', 'mbstring', 'openssl', 'fileinfo', 'sodium']],
            'database_migration_required' => $migrationsChanged,
            'includes_vendor' => $includeVendor,
            'description' => [
                'ro' => $options['description_ro'] ?? "Autentik Market {$version}",
                'hu' => $options['description_hu'] ?? "Autentik Market {$version}",
            ],
            'files' => $hashes,
            'delete' => $delete,
        ];
        $manifest['checksum'] = Manifest::computeChecksum($hashes, $delete);

        File::ensureDirectoryExists($this->distDir());
        $zipPath = $this->distDir()."/autentikmarket-update-{$version}.zip";
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new UpdateException("Nu pot crea {$zipPath}");
        }
        $zip->addFromString('update.json', $json);
        foreach (['ro' => 'release-notes.md', 'hu' => 'release-notes.hu.md'] as $lang => $name) {
            $notes = $this->root."/releases/notes/{$version}".($lang === 'hu' ? '.hu' : '').'.md';
            if (is_file($notes)) {
                $zip->addFile($notes, $name);
            }
        }
        if ($signingKey) {
            $zip->addFromString('update.sig', SignatureVerifier::sign($json, $signingKey));
        }
        foreach ($files as $rel => $abs) {
            $zip->addFile($abs, 'files/'.$rel);
        }
        $zip->close();

        $this->saveInventory($version, $hashes + ($includeVendor ? [] : $this->vendorInventory($previous)));

        return ['path' => $zipPath, 'manifest' => $manifest, 'files' => count($hashes), 'deleted' => count($delete), 'vendor' => $includeVendor];
    }

    /** Pachet complet pentru instalarea inițială pe cPanel (include vendor/ și public/build/). */
    public function buildInstall(string $version): array
    {
        $this->assertBuildable($version);
        if (! is_file($this->root.'/vendor/autoload.php')) {
            throw new UpdateException('vendor/ lipsește — rulați „composer install --no-dev --optimize-autoloader” înainte.');
        }
        $files = $this->collect(array_merge(self::APP_PATHS, self::INSTALL_EXTRA, ['vendor']));
        // Fără date locale: nici .env, nici loguri, sesiuni, cache, upload-uri, backup-uri.
        $files = array_filter($files, fn ($rel) => $rel !== '.env'
            && (! preg_match('~^storage/(logs|framework/(cache|sessions|views|testing)|app/(public|private))/.+~', $rel)
                || preg_match('~/(\.gitignore|\.htaccess)$~', $rel)), ARRAY_FILTER_USE_KEY);

        $hashes = [];
        foreach ($files as $rel => $abs) {
            $hashes[$rel] = hash_file('sha256', $abs);
        }
        ksort($hashes, SORT_STRING);

        File::ensureDirectoryExists($this->distDir());
        $zipPath = $this->distDir()."/autentikmarket-install-{$version}.zip";
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new UpdateException("Nu pot crea {$zipPath}");
        }
        foreach ($files as $rel => $abs) {
            $zip->addFile($abs, 'autentikmarket/'.$rel);
        }
        foreach (['storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $d) {
            $zip->addEmptyDir('autentikmarket/'.$d);
        }
        $zip->close();

        $this->saveInventory($version, $hashes);

        return ['path' => $zipPath, 'files' => count($hashes)];
    }

    // ---------------------------------------------------------------------

    protected function assertBuildable(string $version): void
    {
        if (! Semver::isValid($version)) {
            throw new UpdateException('Versiunea trebuie să fie de forma X.Y.Z (Semantic Versioning).');
        }
        $current = trim((string) @file_get_contents($this->root.'/VERSION'));
        if ($current !== $version) {
            throw new UpdateException("Fișierul VERSION conține „{$current}”. Actualizați-l la {$version} înainte de build (vezi RELEASE-PROCESS.md).");
        }
        if (! is_file($this->root.'/public/build/manifest.json')) {
            throw new UpdateException('public/build/manifest.json lipsește — rulați „npm run build” înainte.');
        }
    }

    /** @return array<string,string> rel => abs */
    protected function collect(array $paths): array
    {
        $out = [];
        foreach ($paths as $p) {
            $abs = $this->root.'/'.$p;
            if (is_file($abs)) {
                $out[$p] = $abs;
            } elseif (is_dir($abs)) {
                $finder = (new Finder)->files()->in($abs)->ignoreDotFiles(false)->ignoreVCS(true)
                    ->exclude(['node_modules', 'releases'])
                    ->notName(['.DS_Store', 'Thumbs.db', '*.log', '.phpunit.result.cache']);
                foreach ($finder as $f) {
                    if ($f->isLink()) {
                        continue;
                    }
                    $rel = $p.'/'.str_replace('\\', '/', $f->getRelativePathname());
                    $out[$rel] = $f->getRealPath();
                }
            }
        }
        ksort($out, SORT_STRING);

        return $out;
    }

    protected function inPaths(string $rel, array $paths): bool
    {
        foreach ($paths as $p) {
            if ($rel === $p || str_starts_with($rel, $p.'/')) {
                return true;
            }
        }

        return false;
    }

    protected function latestInventoryVersion(string $exclude): ?string
    {
        $versions = [];
        foreach (glob($this->manifestsDir().'/*.json') ?: [] as $f) {
            $v = basename($f, '.json');
            if (Semver::isValid($v) && Semver::compare($v, $exclude) < 0) {
                $versions[] = $v;
            }
        }
        usort($versions, 'version_compare');

        return $versions ? end($versions) : null;
    }

    protected function loadInventory(string $version): array
    {
        $f = $this->manifestsDir()."/{$version}.json";
        if (! is_file($f)) {
            throw new UpdateException("Inventarul versiunii {$version} lipsește: {$f}");
        }

        return json_decode((string) file_get_contents($f), true) ?: ['files' => []];
    }

    protected function vendorInventory(array $previous): array
    {
        return array_filter($previous['files'] ?? [], fn ($k) => str_starts_with($k, 'vendor/'), ARRAY_FILTER_USE_KEY);
    }

    protected function saveInventory(string $version, array $hashes): void
    {
        ksort($hashes, SORT_STRING);
        File::ensureDirectoryExists($this->manifestsDir());
        file_put_contents($this->manifestsDir()."/{$version}.json", json_encode([
            'version' => $version,
            'generated_at' => now()->toIso8601String(),
            'files' => $hashes,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
}
