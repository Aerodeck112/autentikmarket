<?php

namespace App\Contracts;

use App\Enums\Language;

/**
 * Orice destinatar de comunicări automate (User, Producer) își declară limba.
 * Motorul de notificări NU trimite mesaje bilingve când limba este cunoscută.
 */
interface HasPreferredLanguage
{
    public function preferredLanguage(): Language;

    public function notificationEmail(): ?string;

    public function notificationName(): string;
}
