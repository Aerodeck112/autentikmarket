<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Review extends Model
{
    protected $guarded = ['id', 'status', 'moderated_by', 'moderated_at'];

    protected $casts = [
        'status' => ReviewStatus::class,
        'moderated_at' => 'datetime',
        'rating' => 'integer',
    ];

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', ReviewStatus::Approved->value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /** Afișare publică discretă: „Andrei P.” */
    public function authorDisplayName(): string
    {
        $u = $this->user;
        if (! $u || $u->anonymized_at) {
            return __('Vizitator');
        }

        return trim($u->first_name.' '.mb_substr((string) $u->last_name, 0, 1).'.');
    }
}
