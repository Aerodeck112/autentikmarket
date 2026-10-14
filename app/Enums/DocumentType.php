<?php

namespace App\Enums;

enum DocumentType: string
{
    use HasLabel;

    case Company = 'company';
    case Authorization = 'authorization';
    case Dsvsa = 'dsvsa';
    case Certification = 'certification';
    case Photo = 'photo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Company => __('Documente firmă'),
            self::Authorization => __('Autorizații'),
            self::Dsvsa => __('Înregistrare / autorizare DSVSA'),
            self::Certification => __('Certificări'),
            self::Photo => __('Fotografii producție'),
            self::Other => __('Alte documente'),
        };
    }
}
