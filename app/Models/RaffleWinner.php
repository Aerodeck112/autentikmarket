<?php

namespace App\Models;

use App\Enums\WinnerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaffleWinner extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'status' => WinnerStatus::class,
        'claimed_at' => 'datetime',
    ];

    public function draw(): BelongsTo
    {
        return $this->belongsTo(RaffleDraw::class, 'raffle_draw_id');
    }

    public function raffle(): BelongsTo
    {
        return $this->belongsTo(Raffle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(RafflePrize::class, 'raffle_prize_id');
    }
}
