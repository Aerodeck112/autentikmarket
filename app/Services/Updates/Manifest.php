<?php

namespace App\Services\Updates;

use App\Support\Semver;

/**
 * update.json — manifestul pachetului (vezi docs/UPDATE-PACKAGES.md).
 */
final class Manifest
{
    public function __construct(
        public readonly int $format,
        public readonly string $application,
        public readonly string $version,
        public readonly string $minimumVersion,
        public readonly ?string $releaseDate,
        public readonly array $requires,
        public readonly bool $migrationRequired,
        public readonly bool $includesVendor,
        public readonly array $description,
        /** @var array<string,string> cale relativă => sha256 */
        public readonly array $files,
        /** @var list<string> */
        public readonly array $delete,
        public readonly string $checksum,
        public readonly array $raw,
    ) {}

    /** @throws UpdateException */
    public static function fromJson(string $json): self
    {
        try {
            $d = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new UpdateException('update.json nu este JSON valid: '.$e->getMessage());
        }
        if (! is_array($d)) {
            throw new UpdateException('update.json invalid.');
        }

        foreach (['application', 'version', 'minimum_version', 'files', 'checksum'] as $key) {
            if (! array_key_exists($key, $d)) {
                throw new UpdateException("update.json: lipsește câmpul obligatoriu „{$key}”.");
            }
        }
        if (! Semver::isValid($d['version'] ?? null)) {
            throw new UpdateException('update.json: „version” nu respectă Semantic Versioning (X.Y.Z).');
        }
        if (! Semver::isValid($d['minimum_version'] ?? null)) {
            throw new UpdateException('update.json: „minimum_version” nu respectă Semantic Versioning (X.Y.Z).');
        }
        if (! is_array($d['files']) || array_is_list($d['files']) && $d['files'] !== []) {
            throw new UpdateException('update.json: „files” trebuie să fie un obiect {cale: sha256}.');
        }
        foreach ($d['files'] as $path => $hash) {
            if (! is_string($path) || ! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/', $hash)) {
                throw new UpdateException("update.json: hash SHA-256 invalid pentru „{$path}”.");
            }
        }
        $delete = $d['delete'] ?? [];
        if (! is_array($delete) || ! array_is_list($delete) || array_filter($delete, fn ($p) => ! is_string($p))) {
            throw new UpdateException('update.json: „delete” trebuie să fie o listă de căi.');
        }

        return new self(
            format: (int) ($d['format'] ?? 1),
            application: (string) $d['application'],
            version: $d['version'],
            minimumVersion: $d['minimum_version'],
            releaseDate: isset($d['release_date']) ? (string) $d['release_date'] : null,
            requires: is_array($d['requires'] ?? null) ? $d['requires'] : [],
            migrationRequired: (bool) ($d['database_migration_required'] ?? false),
            includesVendor: (bool) ($d['includes_vendor'] ?? false),
            description: is_array($d['description'] ?? null) ? $d['description'] : ['ro' => (string) ($d['description'] ?? '')],
            files: $d['files'],
            delete: array_values($delete),
            checksum: (string) $d['checksum'],
            raw: $d,
        );
    }

    /**
     * Checksum-ul pachetului: SHA-256 peste lista canonică „cale:hash” + „delete:cale”, sortată.
     */
    public static function computeChecksum(array $files, array $delete): string
    {
        ksort($files, SORT_STRING);
        $lines = [];
        foreach ($files as $path => $hash) {
            $lines[] = $path.':'.$hash;
        }
        $del = $delete;
        sort($del, SORT_STRING);
        foreach ($del as $path) {
            $lines[] = 'delete:'.$path;
        }

        return hash('sha256', implode("\n", $lines));
    }
}
