<?php

namespace App\Enums;

enum NotificationStatus: string
{
    use HasLabel;

    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('În coadă'),
            self::Processing => __('Se procesează'),
            self::Sent => __('Trimis'),
            self::Failed => __('Eșuat'),
            self::Skipped => __('Omis'),
        };
    }
}
