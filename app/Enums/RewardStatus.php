<?php

namespace App\Enums;

enum RewardStatus: string
{
    use HasLabel;

    case Available = 'available';
    case Redeemed = 'redeemed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Disponibilă'),
            self::Redeemed => __('Ridicată'),
            self::Expired => __('Expirată'),
            self::Cancelled => __('Anulată'),
        };
    }
}
