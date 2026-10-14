<?php

namespace App\Console\Commands;

use App\Enums\UpdateStatus;
use App\Models\User;
use App\Services\Updates\UpdateManager;
use Illuminate\Console\Command;

class UpdateInstallCommand extends Command
{
    protected $signature = 'update:install {package : Calea către autentikmarket-update-X.Y.Z.zip} {--yes : Fără confirmare} {--confirm-destructive : Confirmă migrațiile potențial distructive}';

    protected $description = 'Instalează un pachet de update din linia de comandă (alternativă la CRM → Sistem → Actualizări).';

    public function handle(UpdateManager $manager): int
    {
        $path = $this->argument('package');
        if (! is_file($path)) {
            $this->error('Fișierul nu există.');

            return self::FAILURE;
        }
        $update = $manager->receive($path, basename($path), null);
        if ($update->status !== UpdateStatus::Validated) {
            $this->error('Pachet respins: '.$update->message);

            return self::FAILURE;
        }

        $this->info("Autentik Market {$update->version} (instalată: {$update->previous_version})");
        if ($update->release_notes) {
            $this->line($update->release_notes);
        }
        if ($update->destructive_migrations) {
            $this->warn('Atenție: pachetul conține migrații potențial distructive:');
            foreach ($update->destructive_migrations['files'] as $f) {
                $this->line(' - '.$f['file'].' ('.implode(', ', $f['matches']).')');
            }
            if (! $this->option('confirm-destructive')) {
                $this->error('Rulați din nou cu --confirm-destructive după verificare.');

                return self::FAILURE;
            }
            $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();
            $admin && $manager->confirmDestructive($update, $admin);
            $update->refresh();
        }
        if (! $this->option('yes') && ! $this->confirm('Instalez actualizarea?')) {
            return self::FAILURE;
        }

        $result = $manager->runAll($update, null, function ($r) {
            $this->line(($r['ok'] ? '<info>✔</info> ' : '<error>✘</error> ').$r['step'].': '.$r['message']);
        });

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
