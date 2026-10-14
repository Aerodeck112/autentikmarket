<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyRule extends Model
{
    use HasTranslations;

    protected $attributes = [
        'active' => true,
    ];

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean', 'threshold' => 'integer'];

    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }
}
