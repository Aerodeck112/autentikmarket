<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LayoutSlot extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'x' => 'float', 'y' => 'float', 'width' => 'float', 'height' => 'float', 'rotation' => 'float',
        'price' => 'decimal:2',
        'has_electricity' => 'boolean',
        'is_blocked' => 'boolean',
        'features' => 'array',
    ];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(VenueLayout::class, 'venue_layout_id');
    }
}
