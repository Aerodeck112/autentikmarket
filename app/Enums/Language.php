<?php

namespace App\Enums;

enum Language: string
{
    use HasLabel;

    case Ro = 'ro';
    case Hu = 'hu';

    public function label(): string
    {
        return match ($this) {
            self::Ro => 'Română',
            self::Hu => 'Magyar',
        };
    }

    public function badge(): string
    {
        return strtoupper($this->value);
    }

    public static function fromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom(strtolower((string) $value)) ?? self::default();
    }

    public static function default(): self
    {
        return self::Ro;
    }
}
