<?php

namespace App\Services\Checkin;

use App\Models\Checkin;
use Illuminate\Support\Collection;

final class CheckinResult
{
    public function __construct(
        public readonly Checkin $checkin,
        public readonly bool $created,
        public readonly int $visits,
        public readonly Collection $newRewards,
    ) {}
}
