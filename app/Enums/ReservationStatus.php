<?php

namespace App\Enums;

enum ReservationStatus: string
{
    use HasLabel;

    case Hold = 'hold';
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Hold => __('Rezervare temporară'),
            self::Requested => __('În așteptarea aprobării'),
            self::Approved => __('Aprobată'),
            self::Rejected => __('Respinsă'),
            self::Expired => __('Expirată'),
            self::Cancelled => __('Anulată'),
            self::Released => __('Eliberată'),
        };
    }
}
