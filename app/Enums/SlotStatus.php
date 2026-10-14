<?php

namespace App\Enums;

enum SlotStatus: string
{
    use HasLabel;

    case Available = 'available';
    case Hold = 'hold';
    case Requested = 'requested';
    case Reserved = 'reserved';
    case Occupied = 'occupied';
    case Blocked = 'blocked';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Disponibil'),
            self::Hold => __('Rezervat temporar'),
            self::Requested => __('Cerere trimisă'),
            self::Reserved => __('Rezervat'),
            self::Occupied => __('Ocupat'),
            self::Blocked => __('Blocat'),
            self::Unavailable => __('Indisponibil'),
        };
    }
}
