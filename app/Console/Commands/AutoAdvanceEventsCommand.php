<?php

namespace App\Console\Commands;

use App\Services\Events\EventLifecycleService;
use Illuminate\Console\Command;

class AutoAdvanceEventsCommand extends Command
{
    protected $signature = 'events:auto-advance';

    protected $description = 'Deschide/închide înscrierile după date, activează târgul în ziua lui și îl marchează încheiat a doua zi.';

    public function handle(EventLifecycleService $service): int
    {
        $r = $service->autoAdvance();
        $this->line(json_encode($r));

        return self::SUCCESS;
    }
}
