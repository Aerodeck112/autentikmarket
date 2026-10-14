<?php

namespace App\Console\Commands;

use App\Models\ApplicationUpdate;
use App\Services\Updates\UpdateManager;
use Illuminate\Console\Command;

class UpdateRollbackCommand extends Command
{
    protected $signature = 'update:rollback {uuid : UUID-ul actualizării (vezi CRM → Sistem → Actualizări → Istoric)}';

    protected $description = 'Restaurează fișierele aplicației din backup-ul unei actualizări (baza de date nu este modificată).';

    public function handle(UpdateManager $manager): int
    {
        $update = ApplicationUpdate::where('uuid', $this->argument('uuid'))->firstOrFail();
        if (! $this->confirm("Restaurez fișierele de dinaintea actualizării {$update->version}?")) {
            return self::FAILURE;
        }
        $ok = $manager->rollback($update);
        $ok ? $this->info('Rollback reușit.') : $this->error('Rollback eșuat — vezi istoricul actualizării.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
