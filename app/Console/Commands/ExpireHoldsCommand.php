<?php

namespace App\Console\Commands;

use App\Services\Reservations\StandReservationService;
use Illuminate\Console\Command;

class ExpireHoldsCommand extends Command
{
    protected $signature = 'reservations:expire-holds';

    protected $description = 'Eliberează standurile cu hold temporar expirat.';

    public function handle(StandReservationService $service): int
    {
        $n = $service->expireHolds();
        $n && $this->info("Hold-uri expirate: {$n}");

        return self::SUCCESS;
    }
}
