<?php

namespace App\Enums;

enum WinnerStatus: string
{
    use HasLabel;

    case Won = 'won';
    case Claimed = 'claimed';
    case Forfeited = 'forfeited';

    public function label(): string
    {
        return match ($this) {
            self::Won => __('Câștigător'),
            self::Claimed => __('Premiu ridicat'),
            self::Forfeited => __('Premiu pierdut'),
        };
    }
}
