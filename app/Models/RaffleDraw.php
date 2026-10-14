<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RaffleDraw extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'performed_at' => 'datetime',
        'voided_at' => 'datetime',
        'participants_snapshot' => 'array',
    ];

    public function raffle(): BelongsTo
    {
        return $this->belongsTo(Raffle::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function winners(): HasMany
    {
        return $this->hasMany(RaffleWinner::class)->orderBy('position');
    }
}
