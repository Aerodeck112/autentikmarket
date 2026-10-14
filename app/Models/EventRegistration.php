<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventRegistration extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'status' => RegistrationStatus::class,
        'needs_electricity' => 'boolean',
        'needs_table' => 'boolean',
        'needs_tent' => 'boolean',
        'attended' => 'boolean',
        'data_confirmed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'thanked_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StandReservation::class);
    }

    public function activeReservation(): HasOne
    {
        return $this->hasOne(StandReservation::class)->whereNotNull('active_slot_id')->latest('id');
    }

    public function annexes(): HasMany
    {
        return $this->hasMany(Annex::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
