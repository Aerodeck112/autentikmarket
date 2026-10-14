<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class VenueLayout extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_master' => 'boolean',
        'width_m' => 'float',
        'height_m' => 'float',
        'background_opacity' => 'float',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(LayoutSlot::class)->orderBy('sort_order')->orderBy('code');
    }

    public function backgroundUrl(): ?string
    {
        return $this->background_path ? Storage::disk('public')->url($this->background_path) : null;
    }
}
