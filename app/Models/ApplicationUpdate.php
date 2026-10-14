<?php

namespace App\Models;

use App\Enums\UpdateStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApplicationUpdate extends Model
{
    use HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => UpdateStatus::class,
        'manifest' => 'array',
        'log' => 'array',
        'destructive_migrations' => 'array',
        'signature_valid' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function appendLog(string $step, string $message, string $level = 'info'): void
    {
        $log = $this->log ?? [];
        $log[] = ['at' => now()->toIso8601String(), 'step' => $step, 'level' => $level, 'message' => $message];
        $this->log = $log;
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    public function backup(): HasOne
    {
        return $this->hasOne(UpdateBackup::class);
    }
}
