<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Implementarea reală: comenzi Artisan rulate in-process (fără exec/shell — compatibil cPanel).
 */
class ArtisanRuntime implements ApplicationRuntime
{
    public function down(): void
    {
        Artisan::call('down', ['--retry' => 60, '--render' => 'errors::503']);
    }

    public function up(): void
    {
        Artisan::call('up');
    }

    public function isDown(): bool
    {
        return app()->isDownForMaintenance();
    }

    public function migrate(): string
    {
        $code = Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();
        if ($code !== 0) {
            throw new UpdateException("Migrațiile au eșuat:\n".$output);
        }

        return $output;
    }

    public function pendingMigrations(): array
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return ['(tabela migrations lipsește)'];
        }
        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
        $ran = $migrator->getRepository()->getRan();

        return array_values(array_diff(array_keys($files), $ran));
    }

    public function clearCaches(): void
    {
        Artisan::call('optimize:clear');
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    public function optimize(): void
    {
        if (app()->environment('local')) {
            return; // în development nu cache-uim config/rute
        }
        $code = Artisan::call('optimize');
        if ($code !== 0) {
            throw new UpdateException("Reconstruirea cache-ului a eșuat:\n".Artisan::output());
        }
        // Fișierele de cache tocmai rescrise nu trebuie servite din opcache în versiunea veche.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    public function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
