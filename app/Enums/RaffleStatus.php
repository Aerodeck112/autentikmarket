<?php

namespace App\Enums;

enum RaffleStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Drawn = 'drawn';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Ciornă'),
            self::Open => __('Înscrieri deschise'),
            self::Closed => __('Înscrieri închise'),
            self::Drawn => __('Extrasă'),
            self::Cancelled => __('Anulată'),
        };
    }
}
