<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\App;

class Venue extends Model
{
    use HasTranslations;

    protected $attributes = [
        'active' => true,
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function layouts(): HasMany
    {
        return $this->hasMany(VenueLayout::class);
    }

    public function masterLayout(): HasOne
    {
        return $this->hasOne(VenueLayout::class)->where('is_master', true);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function localizedCity(): string
    {
        return App::getLocale() === 'hu' && $this->city_hu ? $this->city_hu : $this->city;
    }

    public function localizedAddress(): ?string
    {
        return App::getLocale() === 'hu' && $this->address_hu ? $this->address_hu : $this->address;
    }
}
