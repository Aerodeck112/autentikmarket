<?php

namespace App\Enums;

enum TaskPriority: string
{
    use HasLabel;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Scăzută'),
            self::Normal => __('Normală'),
            self::High => __('Ridicată'),
            self::Critical => __('Critică'),
        };
    }
}
