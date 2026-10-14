<?php

namespace App\Enums;

enum UpdateStatus: string
{
    use HasLabel;

    case Uploaded = 'uploaded';
    case Validated = 'validated';
    case BackupCreated = 'backup_created';
    case Installing = 'installing';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => __('Încărcat'),
            self::Validated => __('Validat'),
            self::BackupCreated => __('Backup creat'),
            self::Installing => __('Se instalează'),
            self::Completed => __('Finalizat'),
            self::Failed => __('Eșuat'),
            self::RolledBack => __('Restaurat'),
            self::Rejected => __('Respins'),
        };
    }
}
