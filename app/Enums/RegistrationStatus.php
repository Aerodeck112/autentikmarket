<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Ciornă'),
            self::Submitted => __('Trimisă'),
            self::Approved => __('Aprobată'),
            self::Rejected => __('Respinsă'),
            self::Cancelled => __('Anulată'),
        };
    }
}
