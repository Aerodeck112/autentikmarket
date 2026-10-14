<?php

namespace App\Enums;

enum ReviewStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('În moderare'),
            self::Approved => __('Aprobată'),
            self::Rejected => __('Respinsă'),
        };
    }
}
