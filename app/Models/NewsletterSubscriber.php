<?php

namespace App\Models;

use App\Contracts\HasPreferredLanguage;
use App\Enums\Language;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model implements HasPreferredLanguage
{
    protected $guarded = ['id'];

    protected $casts = [
        'preferred_language' => Language::class,
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function preferredLanguage(): Language
    {
        return $this->preferred_language instanceof Language ? $this->preferred_language : Language::fromMixed($this->preferred_language);
    }

    public function notificationEmail(): ?string
    {
        return $this->email;
    }

    public function notificationName(): string
    {
        return '';
    }
}
