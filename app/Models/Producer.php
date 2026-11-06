<?php

namespace App\Models;

use App\Contracts\HasPreferredLanguage;
use App\Enums\Language;
use App\Enums\ProducerStatus;
use App\Enums\ReviewStatus;
use App\Models\Concerns\HasTranslations;
use Database\Factories\ProducerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Producer extends Model implements HasPreferredLanguage
{
    /** @use HasFactory<ProducerFactory> */
    use HasFactory, HasTranslations;

    protected $guarded = ['id', 'qr_token', 'status', 'approved_at', 'approved_by', 'status_changed_at'];

    protected $hidden = ['iban', 'qr_token', 'internal_notes'];

    protected $attributes = [
        'is_public' => true,
        'status' => 'new',
        'preferred_language' => 'ro',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProducerStatus::class,
            'preferred_language' => Language::class,
            'status_changed_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_public' => 'boolean',
            'campaigns_opt_out' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Producer $p) {
            $p->qr_token ??= Str::random(40);
            $p->status ??= ProducerStatus::New;
            $p->preferred_language ??= Language::default();
            if (empty($p->slug)) {
                $p->slug = static::uniqueSlug($p->brand_name);
            }
        });
        // Categoria principală face parte mereu și din lista de categorii.
        static::saved(function (Producer $p) {
            if ($p->producer_category_id && ($p->wasRecentlyCreated || $p->wasChanged('producer_category_id'))) {
                $p->categories()->syncWithoutDetaching([$p->producer_category_id]);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'producator';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    // ---- Limbă / notificări ------------------------------------------------

    public function preferredLanguage(): Language
    {
        return $this->preferred_language instanceof Language
            ? $this->preferred_language
            : Language::fromMixed($this->preferred_language);
    }

    public function notificationEmail(): ?string
    {
        return $this->email ?: $this->user?->email;
    }

    public function notificationName(): string
    {
        return $this->representative_name ?: $this->brand_name;
    }

    // ---- Scopes ------------------------------------------------------------

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', ProducerStatus::Approved->value);
    }

    public function scopePublicListing(Builder $q): Builder
    {
        return $q->approved()->where('is_public', true);
    }

    public function isApproved(): bool
    {
        return $this->status === ProducerStatus::Approved;
    }

    // ---- Media -------------------------------------------------------------

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->brand_name)) ?: [];

        return mb_strtoupper(mb_substr($words[0] ?? 'A', 0, 1).mb_substr($words[1] ?? '', 0, 1));
    }

    // ---- Relații -----------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** „Reghin, jud. Mureș” / „Reghin, Mureș megye” — localitatea și județul de unde vine producătorul. */
    public function locationLabel(?string $locale = null): string
    {
        $county = trim((string) $this->county);
        if ($county !== '' && ! preg_match('/^(jud\.?|județul|judetul)\s/iu', $county) && ! str_contains(mb_strtolower($county), 'megye')) {
            $county = ($locale ?? app()->getLocale()) === 'hu' ? $county.' megye' : 'jud. '.$county;
        }

        return implode(', ', array_filter([trim((string) $this->city), $county]));
    }

    /** Categoria principală (afișată pe card). */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProducerCategory::class, 'producer_category_id');
    }

    /** Toate categoriile producătorului (inclusiv cea principală). */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProducerCategory::class, 'producer_category_producer')->orderBy('sort_order');
    }

    /**
     * Salvează categoriile alese; cea principală rămâne aceeași dacă e încă bifată, altfel prima din listă.
     *
     * @param  array<int, int|string>  $ids
     */
    public function syncCategories(array $ids): void
    {
        $ids = ProducerCategory::whereIn('id', array_map('intval', $ids))->orderBy('sort_order')->pluck('id')->all();
        $this->categories()->sync($ids);
        $primary = in_array((int) $this->producer_category_id, $ids, true) ? (int) $this->producer_category_id : ($ids[0] ?? null);
        if ($primary !== $this->producer_category_id) {
            $this->forceFill(['producer_category_id' => $primary])->save();
        }
        $this->unsetRelation('categories')->unsetRelation('category');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProducerDocument::class);
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(ProducerDocumentRequest::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ProducerStatusHistory::class)->latest('id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StandReservation::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function annexes(): HasMany
    {
        return $this->hasMany(Annex::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', ReviewStatus::Approved->value);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ProducerCustomerInteraction::class);
    }

    public function gallery(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->where('collection', 'gallery')->orderBy('sort_order');
    }
}
