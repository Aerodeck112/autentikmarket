<?php

namespace App\Models;

use App\Enums\BlogStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasTranslations;

    protected $guarded = ['id', 'status', 'published_at'];

    protected $casts = [
        'status' => BlogStatus::class,
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BlogPost $p) {
            $p->status ??= BlogStatus::Draft;
            $p->slug_ro = $p->slug_ro ?: static::uniqueSlug('slug_ro', $p->title_ro);
            $p->slug_hu = $p->slug_hu ?: static::uniqueSlug('slug_hu', $p->title_hu ?: $p->title_ro);
        });
    }

    public static function uniqueSlug(string $column, string $title): string
    {
        $base = Str::slug($title) ?: 'articol';
        $slug = $base;
        $i = 2;
        while (static::where($column, $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', BlogStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function slugFor(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'hu' ? $this->slug_hu : $this->slug_ro;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
