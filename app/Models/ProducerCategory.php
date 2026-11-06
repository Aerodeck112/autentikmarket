<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProducerCategory extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];

    /** Toți producătorii din categorie (principală sau secundară). */
    public function producers(): BelongsToMany
    {
        return $this->belongsToMany(Producer::class, 'producer_category_producer');
    }
}
