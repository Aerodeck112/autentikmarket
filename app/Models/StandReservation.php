<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandReservation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'status' => ReservationStatus::class,
        'hold_expires_at' => 'datetime',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    public function isActive(): bool
    {
        return $this->active_slot_id !== null;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(EventSlot::class, 'event_slot_id');
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
