<?php

use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;

if (! function_exists('lroute')) {
    /**
     * Rută publică localizată: lroute('producers.index') → /ro/producatori sau /hu/termelok.
     */
    function lroute(string $name, mixed $parameters = [], ?string $locale = null, bool $absolute = true): string
    {
        $locale ??= App::getLocale();
        if (! in_array($locale, config('autentik.locales', ['ro', 'hu']), true)) {
            $locale = config('autentik.default_locale', 'ro');
        }

        return route($locale.'.'.$name, $parameters, $absolute);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (! function_exists('tr')) {
    /**
     * Alege varianta RO/HU dintr-o pereche de valori (pentru texte din cod, nu din DB).
     */
    function tr(string $ro, string $hu, ?string $locale = null): string
    {
        return ($locale ?? App::getLocale()) === 'hu' ? $hu : $ro;
    }
}

if (! function_exists('money_ron')) {
    function money_ron(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return __('Tarif la cerere');
        }

        return number_format((float) $amount, 2, ',', '.').' lei';
    }
}

if (! function_exists('format_date_local')) {
    /** Dată lizibilă în limba curentă: „14 noiembrie 2026” / „2026. november 14.” */
    function format_date_local(mixed $date, bool $withWeekday = false, ?string $locale = null): string
    {
        if (! $date) {
            return '';
        }
        $locale ??= App::getLocale();
        $c = Carbon::parse($date)->locale($locale);
        if ($locale === 'hu') {
            return $c->translatedFormat($withWeekday ? 'Y. F j., l' : 'Y. F j.');
        }

        return $c->translatedFormat($withWeekday ? 'l, j F Y' : 'j F Y');
    }
}
