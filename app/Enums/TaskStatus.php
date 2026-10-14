<?php

namespace App\Enums;

enum TaskStatus: string
{
    use HasLabel;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('De făcut'),
            self::InProgress => __('În lucru'),
            self::Done => __('Finalizat'),
            self::Cancelled => __('Anulat'),
        };
    }
}
