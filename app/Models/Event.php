<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Concerns\HasTranslations;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasTranslations;

    protected $guarded = ['id', 'status', 'completed_at', 'post_event_processed_at', 'reminders_sent_at'];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'edition' => 'integer',
            'event_date' => 'date',
            'registration_start' => 'datetime',
            'registration_end' => 'datetime',
            'completed_at' => 'datetime',
            'post_event_processed_at' => 'datetime',
            'reminders_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $e) {
            $e->status ??= EventStatus::Draft;
            if (empty($e->slug)) {
                $e->slug = static::uniqueSlug($e->title_ro.' '.$e->event_date?->format('Y-m-d'));
            }
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'eveniment';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    // ---- Scopes ------------------------------------------------------------

    /** Evenimente vizibile public (nu draft / anulate ascunse). */
    public function scopePublished(Builder $q): Builder
    {
        return $q->whereNotIn('status', [EventStatus::Draft->value]);
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->whereDate('event_date', '>=', now()->toDateString())
            ->whereNotIn('status', [EventStatus::Draft->value, EventStatus::Cancelled->value, EventStatus::Completed->value])
            ->orderBy('event_date');
    }

    // ---- Helpers -----------------------------------------------------------

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->event_date->format('Y-m-d').' '.($this->start_time ?? '08:00:00'), config('app.timezone'));
    }

    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->event_date->format('Y-m-d').' '.($this->end_time ?? '15:00:00'), config('app.timezone'));
    }

    public function isToday(): bool
    {
        return $this->event_date->isToday();
    }

    public function registrationsOpen(): bool
    {
        if ($this->status !== EventStatus::RegistrationsOpen) {
            return false;
        }
        $now = now();
        if ($this->registration_start && $now->lt($this->registration_start)) {
            return false;
        }
        if ($this->registration_end && $now->gt($this->registration_end)) {
            return false;
        }

        return true;
    }

    /** „Ediția a 12-a” (RO) / „12. kiadás” (HU); null dacă ediția nu e numerotată. */
    public function editionLabel(?string $locale = null): ?string
    {
        if (! $this->edition) {
            return null;
        }
        $locale ??= app()->getLocale();
        if ($locale === 'hu') {
            return $this->edition.'. kiadás';
        }

        return $this->edition === 1 ? 'Ediția 1' : 'Ediția a '.$this->edition.'-a';
    }

    /** Etichetă scurtă pentru liste: „Ed. 12 · Târgu Mureș · 07.11.2026”. */
    public function shortLabel(): string
    {
        return collect([$this->edition ? 'Ed. '.$this->edition : null, $this->localizedCity(), $this->event_date?->format('d.m.Y')])->filter()->implode(' · ');
    }

    /** Următorul număr de ediție pentru aceeași locație (sau același oraș, fără locație). */
    public static function nextEdition(?int $venueId, ?string $city): int
    {
        $max = static::query()
            ->when($venueId, fn ($q) => $q->where('venue_id', $venueId), fn ($q) => $q->whereNull('venue_id')->where('city', $city))
            ->max('edition');

        return ((int) $max) + 1;
    }

    public function timeRange(): string
    {
        $s = $this->start_time ? substr($this->start_time, 0, 5) : null;
        $e = $this->end_time ? substr($this->end_time, 0, 5) : null;

        return trim(($s ?? '').($s && $e ? ' – ' : '').($e ?? ''));
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function localizedCity(): string
    {
        return app()->getLocale() === 'hu' && $this->venue?->city_hu ? $this->venue->city_hu : $this->city;
    }

    public function localizedAddress(): ?string
    {
        if (app()->getLocale() === 'hu' && $this->venue?->address_hu && $this->address === $this->venue->address) {
            return $this->venue->address_hu;
        }

        return $this->address;
    }

    // ---- Relații -----------------------------------------------------------

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function layout(): HasOne
    {
        return $this->hasOne(EventLayout::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(EventSlot::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function approvedRegistrations(): HasMany
    {
        return $this->registrations()->where('status', RegistrationStatus::Approved->value);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StandReservation::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ProducerCustomerInteraction::class);
    }

    public function raffles(): HasMany
    {
        return $this->hasMany(Raffle::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->where('collection', 'event_photos')->orderBy('sort_order');
    }

    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
