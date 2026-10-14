<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class EventLayout extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'width_m' => 'float',
        'height_m' => 'float',
        'background_opacity' => 'float',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(EventSlot::class)->orderBy('code');
    }

    public function sourceLayout(): BelongsTo
    {
        return $this->belongsTo(VenueLayout::class, 'source_layout_id');
    }

    public function backgroundUrl(): ?string
    {
        return $this->background_path ? Storage::disk('public')->url($this->background_path) : null;
    }
}
