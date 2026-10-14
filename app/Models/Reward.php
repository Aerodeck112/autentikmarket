<?php

namespace App\Models;

use App\Enums\RewardStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reward extends Model
{
    use HasUuid;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => RewardStatus::class,
        'earned_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LoyaltyRule::class, 'loyalty_rule_id');
    }

    public function earnedEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'earned_event_id');
    }

    public function redemption(): HasOne
    {
        return $this->hasOne(RewardRedemption::class);
    }
}
