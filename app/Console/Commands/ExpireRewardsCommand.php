<?php

namespace App\Console\Commands;

use App\Services\Loyalty\LoyaltyService;
use Illuminate\Console\Command;

class ExpireRewardsCommand extends Command
{
    protected $signature = 'loyalty:expire-rewards';

    protected $description = 'Marchează recompensele expirate.';

    public function handle(LoyaltyService $loyalty): int
    {
        $this->line('Expirate: '.$loyalty->expireRewards());

        return self::SUCCESS;
    }
}
