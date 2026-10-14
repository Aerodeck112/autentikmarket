<?php

namespace App\Models;

use App\Contracts\HasPreferredLanguage;
use App\Enums\Language;
use App\Models\Concerns\HasUuid;
use App\Services\Notifications\NotificationService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class User extends Authenticatable implements HasPreferredLanguage, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuid, Notifiable;

    protected $guarded = ['id', 'uuid', 'member_code', 'card_token', 'remember_token'];

    protected $hidden = ['password', 'remember_token', 'card_token'];

    /** Valori implicite și în memorie (nu doar în DB). */
    protected $attributes = [
        'is_active' => true,
        'newsletter' => false,
        'marketing_consent' => false,
        'preferred_language' => 'ro',
    ];

    /** @var array<string, bool>|null cache per request */
    protected ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'password' => 'hashed',
            'preferred_language' => Language::class,
            'newsletter' => 'boolean',
            'marketing_consent' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->member_code ??= static::generateMemberCode();
            $user->card_token ??= Str::random(40);
            $user->preferred_language ??= Language::default();
        });
    }

    public static function generateMemberCode(): string
    {
        // Fără caractere ambigue (0/O, 1/I) — ușor de dictat la check-in manual.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = 'AM-';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('member_code', $code)->exists());

        return $code;
    }

    public function regenerateCardToken(): void
    {
        $this->forceFill(['card_token' => Str::random(40)])->save();
    }

    // ---- Identitate -------------------------------------------------------

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function preferredLanguage(): Language
    {
        return $this->preferred_language instanceof Language
            ? $this->preferred_language
            : Language::fromMixed($this->preferred_language);
    }

    public function notificationEmail(): ?string
    {
        return $this->anonymized_at ? null : $this->email;
    }

    public function notificationName(): string
    {
        return $this->first_name ?: $this->name;
    }

    // ---- RBAC ---------------------------------------------------------------

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return $this->roles->contains(fn (Role $r) => in_array($r->name, $roles, true));
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->permissionCache === null) {
            $this->permissionCache = [];
            $this->loadMissing('roles.permissions');
            foreach ($this->roles as $role) {
                foreach ($role->permissions as $perm) {
                    $this->permissionCache[$perm->name] = true;
                }
            }
        }

        return isset($this->permissionCache[$permission]);
    }

    public function assignRole(string ...$names): static
    {
        $ids = Role::whereIn('name', $names)->pluck('id');
        $this->roles()->syncWithoutDetaching($ids);
        $this->unsetRelation('roles');
        $this->permissionCache = null;

        return $this;
    }

    public function canAccessCrm(): bool
    {
        return $this->hasPermission('crm.access');
    }

    public function isStaff(): bool
    {
        return $this->hasPermission('checkin.perform');
    }

    // ---- Relații --------------------------------------------------------------

    public function producer(): HasOne
    {
        return $this->hasOne(Producer::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    public function raffleEntries(): HasMany
    {
        return $this->hasMany(RaffleEntry::class);
    }

    public function raffleWins(): HasMany
    {
        return $this->hasMany(RaffleWinner::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ProducerCustomerInteraction::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'notifiable_id')
            ->where('notifiable_type', self::class);
    }

    // ---- Notificări de autentificare — trimise prin motorul central, în limba utilizatorului ----

    public function sendPasswordResetNotification($token): void
    {
        app(NotificationService::class)->send('PASSWORD_RESET', $this, [], [
            'action_url' => route('password.reset', ['token' => $token, 'email' => $this->email]),
            'action_label_ro' => 'Resetează parola',
            'action_label_hu' => 'Jelszó visszaállítása',
        ]);
    }

    public function sendEmailVerificationNotification(): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $this->getKey(),
            'hash' => sha1($this->getEmailForVerification()),
        ]);
        app(NotificationService::class)->send('EMAIL_VERIFY', $this, [], [
            'action_url' => $url,
            'action_label_ro' => 'Confirmă adresa de e-mail',
            'action_label_hu' => 'E-mail-cím megerősítése',
        ]);
    }

    public function visitsCount(): int
    {
        return $this->checkins_count ?? $this->checkins()->count();
    }
}
