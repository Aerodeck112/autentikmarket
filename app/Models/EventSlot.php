<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Enums\SlotStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventSlot extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'x' => 'float', 'y' => 'float', 'width' => 'float', 'height' => 'float', 'rotation' => 'float',
        'price' => 'decimal:2',
        'has_electricity' => 'boolean',
        'features' => 'array',
        'status' => SlotStatus::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(EventLayout::class, 'event_layout_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StandReservation::class);
    }

    public function activeReservation(): HasOne
    {
        return $this->hasOne(StandReservation::class, 'active_slot_id');
    }

    public function dimensions(): string
    {
        $f = fn (float $v) => rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');

        return $f($this->width).' × '.$f($this->height).' m';
    }

    public function isSelectable(): bool
    {
        return $this->status === SlotStatus::Available;
    }

    public static function activeReservationStatuses(): array
    {
        return [ReservationStatus::Hold->value, ReservationStatus::Requested->value, ReservationStatus::Approved->value];
    }
}
