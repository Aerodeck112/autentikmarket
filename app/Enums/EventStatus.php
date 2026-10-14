<?php

namespace App\Enums;

enum EventStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Planning = 'planning';
    case RegistrationsOpen = 'registrations_open';
    case RegistrationsClosed = 'registrations_closed';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Ciornă'),
            self::Planning => __('În planificare'),
            self::RegistrationsOpen => __('Înscrieri deschise'),
            self::RegistrationsClosed => __('Înscrieri închise'),
            self::Active => __('În desfășurare'),
            self::Completed => __('Încheiat'),
            self::Cancelled => __('Anulat'),
        };
    }
}
