<?php

namespace App\Support;

use App\Enums\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

class Locales
{
    /** @return list<string> */
    public static function supported(): array
    {
        return config('autentik.locales', ['ro', 'hu']);
    }

    public static function isSupported(?string $locale): bool
    {
        return in_array($locale, static::supported(), true);
    }

    public static function current(): string
    {
        return App::getLocale();
    }

    public static function other(?string $locale = null): string
    {
        return ($locale ?? static::current()) === 'hu' ? 'ro' : 'hu';
    }

    /** Detectează limba preferată: cookie → utilizator → Accept-Language → implicit. */
    public static function detect(Request $request): string
    {
        $cookie = $request->cookie('locale');
        if (static::isSupported($cookie)) {
            return $cookie;
        }
        if ($user = $request->user()) {
            return $user->preferredLanguage()->value;
        }
        $preferred = $request->getPreferredLanguage(static::supported());

        return static::isSupported($preferred) ? $preferred : config('autentik.default_locale', 'ro');
    }

    /**
     * URL-ul paginii curente în altă limbă (pentru switcher și hreflang).
     * Funcționează pentru rutele publice localizate: "{locale}.{name}".
     */
    public static function switchUrl(string $target): string
    {
        $route = Route::current();
        $name = $route?->getName();

        if ($name && preg_match('/^(ro|hu)\.(.+)$/', $name, $m)) {
            $params = $route->parameters();
            // Parametri localizați (ex. slug articol blog) se transformă prin resolver-ul rutei.
            $params = LocalizedRouteParameters::translate($m[2], $params, $target);
            $targetName = $target.'.'.$m[2];
            if (Route::has($targetName)) {
                return route($targetName, $params);
            }
        }

        return route($target.'.home');
    }

    public static function languageEnum(?string $locale = null): Language
    {
        return Language::fromMixed($locale ?? static::current());
    }
}
