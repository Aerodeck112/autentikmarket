<?php

namespace App\Models;

use App\Enums\RaffleStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Raffle extends Model
{
    use HasTranslations;

    protected $attributes = [
        'requires_checkin' => true,
        'auto_enroll_on_checkin' => true,
        'min_visits' => 0,
        'winners_count' => 1,
    ];

    protected $guarded = ['id', 'status'];

    protected $casts = [
        'status' => RaffleStatus::class,
        'entry_start' => 'datetime',
        'entry_end' => 'datetime',
        'draw_at' => 'datetime',
        'requires_checkin' => 'boolean',
        'auto_enroll_on_checkin' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Raffle $r) => $r->status ??= RaffleStatus::Draft);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(RafflePrize::class)->orderBy('sort_order');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RaffleEntry::class);
    }

    public function draws(): HasMany
    {
        return $this->hasMany(RaffleDraw::class);
    }

    public function currentDraw(): HasOne
    {
        return $this->hasOne(RaffleDraw::class)->where('status', 'valid')->latest('id');
    }

    public function winners(): HasMany
    {
        return $this->hasMany(RaffleWinner::class);
    }

    public function acceptsEntries(): bool
    {
        if ($this->status !== RaffleStatus::Open) {
            return false;
        }
        $now = now();

        return (! $this->entry_start || $now->gte($this->entry_start))
            && (! $this->entry_end || $now->lte($this->entry_end));
    }
}
