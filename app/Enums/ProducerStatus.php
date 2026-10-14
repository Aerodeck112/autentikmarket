<?php

namespace App\Enums;

enum ProducerStatus: string
{
    use HasLabel;

    case New = 'new';
    case Incomplete = 'incomplete';
    case DocumentsSubmitted = 'documents_submitted';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => __('Nou'),
            self::Incomplete => __('Date incomplete'),
            self::DocumentsSubmitted => __('Documente trimise'),
            self::InReview => __('În verificare'),
            self::Approved => __('Aprobat'),
            self::Rejected => __('Respins'),
        };
    }
}
