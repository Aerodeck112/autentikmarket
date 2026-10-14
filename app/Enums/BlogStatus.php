<?php

namespace App\Enums;

enum BlogStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Ciornă'),
            self::Review => __('În revizuire'),
            self::Published => __('Publicat'),
        };
    }
}
