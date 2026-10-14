<?php

namespace App\Console\Commands;

use App\Services\Updates\ReleaseBuilder;
use Illuminate\Console\Command;

class ReleaseBuildCommand extends Command
{
    protected $signature = 'release:build
        {version : Versiunea (X.Y.Z) — trebuie să corespundă fișierului VERSION}
        {--install : Construiește pachetul complet de instalare inițială}
        {--from= : Versiunea anterioară (implicit: ultimul inventar din releases/manifests)}
        {--min= : minimum_version (implicit = --from)}
        {--with-vendor : Forțează includerea vendor/}
        {--without-vendor : Exclude vendor/ chiar dacă composer.lock s-a schimbat}
        {--sign-key-file= : Fișier cu cheia secretă Ed25519 (base64) pentru semnare}';

    protected $description = 'Generează pachetul ZIP compatibil cu Update Manager-ul (rulat pe mașina de dezvoltare).';

    public function handle(): int
    {
        $builder = new ReleaseBuilder(base_path());
        $version = $this->argument('version');

        if ($this->option('install')) {
            $r = $builder->buildInstall($version);
            $this->info("Pachet instalare: {$r['path']} ({$r['files']} fișiere)");

            return self::SUCCESS;
        }

        $vendor = $this->option('with-vendor') ? true : ($this->option('without-vendor') ? false : null);
        $key = null;
        if ($file = $this->option('sign-key-file')) {
            $key = trim((string) file_get_contents($file));
        } elseif ($env = getenv('UPDATE_SIGNING_KEY')) {
            $key = trim($env);
        }

        $r = $builder->buildUpdate($version, $this->option('from'), $vendor, $key, array_filter([
            'minimum_version' => $this->option('min'),
        ]));

        $this->info("Pachet update: {$r['path']}");
        $this->line("  fișiere: {$r['files']} · ștergeri: {$r['deleted']} · vendor: ".($r['vendor'] ? 'DA' : 'nu')
            .' · migrații: '.($r['manifest']['database_migration_required'] ? 'DA' : 'nu')
            .' · semnat: '.($key ? 'DA' : 'nu'));
        $this->line('  minimum_version: '.$r['manifest']['minimum_version']);

        return self::SUCCESS;
    }
}
