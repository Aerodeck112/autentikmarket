<?php

namespace Tests\Support;

use App\Services\Updates\Manifest;
use ZipArchive;

/** Construiește pachete de update (valide sau intenționat invalide) pentru teste. */
class PackageBuilder
{
    public array $files = [];

    public array $delete = [];

    public array $manifest = [];

    public array $rawEntries = [];

    public bool $recomputeChecksum = true;

    public function __construct(public string $version = '1.1.0', public string $minimum = '1.0.0')
    {
        $this->files['VERSION'] = $version."\n";
    }

    public function file(string $path, string $content): static
    {
        $this->files[$path] = $content;

        return $this;
    }

    /** Intrare brută în arhivă (ocolește files/) — pentru teste de securitate. */
    public function raw(string $name, string $content): static
    {
        $this->rawEntries[$name] = $content;

        return $this;
    }

    public function build(string $dir): string
    {
        $hashes = array_map(fn ($c) => hash('sha256', $c), $this->files);
        ksort($hashes);
        $manifest = array_merge([
            'format' => 1,
            'application' => 'autentikmarket',
            'version' => $this->version,
            'minimum_version' => $this->minimum,
            'release_date' => '2026-11-01',
            'requires' => ['php' => '>=8.3'],
            'database_migration_required' => false,
            'description' => ['ro' => 'Test', 'hu' => 'Teszt'],
            'files' => $hashes,
            'delete' => $this->delete,
        ], $this->manifest);
        if ($this->recomputeChecksum || ! isset($manifest['checksum'])) {
            $manifest['checksum'] = Manifest::computeChecksum($manifest['files'], $manifest['delete']);
        }

        $path = $dir.'/pkg-'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('update.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->addFromString('release-notes.md', "- Noutate de test\n");
        foreach ($this->files as $p => $c) {
            $zip->addFromString('files/'.$p, $c);
        }
        foreach ($this->rawEntries as $n => $c) {
            $zip->addFromString($n, $c);
        }
        $zip->close();

        return $path;
    }
}
