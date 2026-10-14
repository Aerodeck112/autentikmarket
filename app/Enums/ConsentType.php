<?php

namespace App\Enums;

enum ConsentType: string
{
    use HasLabel;

    case Terms = 'terms';
    case Privacy = 'privacy';
    case Marketing = 'marketing';
    case Newsletter = 'newsletter';

    public function label(): string
    {
        return match ($this) {
            self::Terms => __('Termeni și condiții'),
            self::Privacy => __('Politica de confidențialitate'),
            self::Marketing => __('Comunicări de marketing'),
            self::Newsletter => __('Newsletter'),
        };
    }
}
