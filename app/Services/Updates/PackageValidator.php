<?php

namespace App\Services\Updates;

use App\Support\AppVersion;
use App\Support\Semver;
use ZipArchive;

/**
 * Validarea completă a unui pachet de update ÎNAINTE de orice modificare a aplicației.
 * Un ZIP corupt, greșit, incompatibil sau malițios nu trece de aici.
 */
class PackageValidator
{
    public const MAX_ENTRIES = 60000;

    public const MAX_UNCOMPRESSED_BYTES = 1_500_000_000;

    public const MAX_RATIO = 250; // protecție zip bomb

    public function __construct(protected PathGuard $guard) {}

    public static function make(): self
    {
        return new self(PathGuard::fromConfig());
    }

    /**
     * @return array{manifest: Manifest, release_notes: string, release_notes_hu: string, signature_valid: ?bool, destructive: list<array{file:string, matches:list<string>}>}
     *
     * @throws UpdateException
     */
    public function validate(string $zipPath, ?string $currentVersion = null): array
    {
        // 1. Extensie + MIME + semnătură binară ZIP
        if (! is_file($zipPath) || ! is_readable($zipPath)) {
            throw new UpdateException('Fișierul pachetului nu poate fi citit.');
        }
        $maxBytes = config('update.max_package_mb', 200) * 1024 * 1024;
        if (filesize($zipPath) > $maxBytes) {
            throw new UpdateException('Pachetul depășește dimensiunea maximă permisă.');
        }
        $fh = fopen($zipPath, 'rb');
        $magic = fread($fh, 4);
        fclose($fh);
        if ($magic !== "PK\x03\x04") {
            throw new UpdateException('Fișierul nu este o arhivă ZIP validă.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($zipPath);
        if (! in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/x-zip', 'application/octet-stream'], true)) {
            throw new UpdateException("Tip MIME neacceptat: {$mime}.");
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new UpdateException('Arhiva ZIP este coruptă sau nu poate fi deschisă.');
        }

        try {
            return $this->validateOpen($zip, $currentVersion ?? AppVersion::current(config('update.target_path')));
        } finally {
            $zip->close();
        }
    }

    protected function validateOpen(ZipArchive $zip, string $currentVersion): array
    {
        // 2. Structură: fiecare intrare verificată (Zip Slip, symlink, criptare, zip bomb)
        if ($zip->numFiles === 0 || $zip->numFiles > self::MAX_ENTRIES) {
            throw new UpdateException('Număr de fișiere invalid în arhivă.');
        }
        $rootAllowed = config('update.root_entries', []);
        $zipFiles = [];
        $totalSize = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);
            if ($stat === false) {
                throw new UpdateException('Intrare ZIP ilizibilă.');
            }
            $name = $stat['name'];
            $this->guard->assertSafeRelative($name);

            if (($stat['encryption_method'] ?? 0) !== 0) {
                throw new UpdateException("Intrările criptate nu sunt acceptate: {$name}");
            }
            if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX) {
                $mode = ($attr >> 16) & 0170000;
                if ($mode === 0120000) {
                    throw new UpdateException("Symlink-urile nu sunt acceptate în pachet: {$name}");
                }
            }

            $totalSize += $stat['size'];
            if ($totalSize > self::MAX_UNCOMPRESSED_BYTES) {
                throw new UpdateException('Conținutul arhivei depășește limita de dimensiune (posibil zip bomb).');
            }
            if ($stat['comp_size'] > 0 && $stat['size'] / $stat['comp_size'] > self::MAX_RATIO && $stat['size'] > 10_000_000) {
                throw new UpdateException("Raport de compresie suspect: {$name}");
            }

            if (str_ends_with($name, '/')) {
                // director: permis doar files/… sau rădăcina files/
                if ($name !== 'files/' && ! str_starts_with($name, 'files/')) {
                    throw new UpdateException("Director neașteptat în arhivă: {$name}");
                }

                continue;
            }

            if (! str_contains($name, '/')) {
                if (! in_array($name, $rootAllowed, true)) {
                    throw new UpdateException("Fișier neașteptat în rădăcina arhivei: {$name}");
                }

                continue;
            }

            if (! str_starts_with($name, 'files/')) {
                throw new UpdateException("Structură invalidă: toate fișierele aplicației trebuie să fie în files/ ({$name}).");
            }
            $relative = substr($name, 6);
            $this->guard->assertWritable($relative);
            $zipFiles[$relative] = $name;
        }

        // 3. Manifest
        $json = $zip->getFromName('update.json');
        if ($json === false) {
            throw new UpdateException('Pachetul nu conține update.json.');
        }
        if (strlen($json) > 20 * 1024 * 1024) {
            throw new UpdateException('update.json este prea mare.');
        }
        $manifest = Manifest::fromJson($json);

        // 4. Application ID
        if ($manifest->application !== config('update.application_id')) {
            throw new UpdateException("Pachetul este pentru altă aplicație („{$manifest->application}”).");
        }

        // 5. Versiune + compatibilitate
        if (! Semver::gt($manifest->version, $currentVersion)) {
            throw new UpdateException("Versiunea pachetului ({$manifest->version}) nu este mai nouă decât versiunea instalată ({$currentVersion}).");
        }
        if (! Semver::gte($currentVersion, $manifest->minimumVersion)) {
            throw new UpdateException("Versiune incompatibilă: pachetul necesită minimum {$manifest->minimumVersion}, instalată este {$currentVersion}.");
        }
        if (isset($manifest->requires['php']) && ! Semver::satisfies(PHP_VERSION, (string) $manifest->requires['php'])) {
            throw new UpdateException('Versiunea PHP '.PHP_VERSION." nu satisface cerința {$manifest->requires['php']}.");
        }
        foreach ((array) ($manifest->requires['extensions'] ?? []) as $ext) {
            if (! extension_loaded((string) $ext)) {
                throw new UpdateException("Extensia PHP necesară lipsește: {$ext}.");
            }
        }

        // 6. Lista de fișiere: arhivă == manifest (în ambele sensuri)
        $manifestPaths = array_keys($manifest->files);
        foreach ($manifestPaths as $p) {
            $this->guard->assertWritable($p);
            if (! isset($zipFiles[$p])) {
                throw new UpdateException("Fișier declarat în manifest dar absent din arhivă: {$p}");
            }
        }
        foreach (array_keys($zipFiles) as $p) {
            if (! array_key_exists($p, $manifest->files)) {
                throw new UpdateException("Fișier nedeclarat în manifest: {$p}");
            }
        }
        foreach ($manifest->delete as $p) {
            $this->guard->assertWritable($p);
            if (array_key_exists($p, $manifest->files)) {
                throw new UpdateException("Cale prezentă simultan în files și delete: {$p}");
            }
        }

        // 7. VERSION din pachet trebuie să corespundă manifestului
        if (! isset($manifest->files['VERSION'])) {
            throw new UpdateException('Pachetul trebuie să conțină files/VERSION.');
        }
        $versionFile = trim((string) $zip->getFromName('files/VERSION'));
        if ($versionFile !== $manifest->version) {
            throw new UpdateException("files/VERSION ({$versionFile}) nu corespunde manifestului ({$manifest->version}).");
        }

        // 8. Checksum global + SHA-256 per fișier
        if (! hash_equals(Manifest::computeChecksum($manifest->files, $manifest->delete), $manifest->checksum)) {
            throw new UpdateException('Checksum-ul pachetului nu corespunde (manifest alterat).');
        }
        foreach ($manifest->files as $rel => $expected) {
            $stream = $zip->getStream('files/'.$rel);
            if ($stream === false) {
                throw new UpdateException("Nu pot citi {$rel} din arhivă.");
            }
            $ctx = hash_init('sha256');
            hash_update_stream($ctx, $stream);
            fclose($stream);
            if (! hash_equals($expected, hash_final($ctx))) {
                throw new UpdateException("Hash SHA-256 incorect pentru {$rel} (fișier corupt sau modificat).");
            }
        }

        // 9. Semnătură Ed25519 (opțională / obligatorie din config)
        $signatureValid = SignatureVerifier::verify($json, $zip->getFromName('update.sig') ?: null);
        if ($signatureValid === false) {
            throw new UpdateException('Semnătura pachetului este invalidă.');
        }
        if ($signatureValid === null && config('update.require_signature')) {
            throw new UpdateException('Pachetul nu este semnat, iar semnătura este obligatorie.');
        }

        // 10. Migrații destructive (cer confirmare explicită)
        $destructive = [];
        foreach ($manifest->files as $rel => $_) {
            if (str_starts_with($rel, 'database/migrations/') && str_ends_with($rel, '.php')) {
                $matches = DestructiveMigrationScanner::scan((string) $zip->getFromName('files/'.$rel));
                if ($matches) {
                    $destructive[] = ['file' => $rel, 'matches' => $matches];
                }
            }
        }

        return [
            'manifest' => $manifest,
            'release_notes' => (string) ($zip->getFromName('release-notes.md') ?: ''),
            'release_notes_hu' => (string) ($zip->getFromName('release-notes.hu.md') ?: ''),
            'signature_valid' => $signatureValid,
            'destructive' => $destructive,
        ];
    }
}
