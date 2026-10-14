<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProducerDocument extends Model
{
    use HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => DocumentType::class,
        'reviewed_at' => 'datetime',
        'expires_at' => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
