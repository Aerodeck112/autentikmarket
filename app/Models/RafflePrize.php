<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RafflePrize extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];

    public function raffle(): BelongsTo
    {
        return $this->belongsTo(Raffle::class);
    }
}
