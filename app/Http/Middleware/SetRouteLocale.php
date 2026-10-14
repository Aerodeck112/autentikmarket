<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutele publice /ro/... și /hu/... setează limba din URL (și o rețin în cookie).
 */
class SetRouteLocale
{
    public function handle(Request $request, Closure $next, string $locale): Response
    {
        if (Locales::isSupported($locale)) {
            App::setLocale($locale);
            Carbon::setLocale($locale);
            $request->session()->put('locale', $locale);
            if ($request->cookie('locale') !== $locale) {
                Cookie::queue(Cookie::make('locale', $locale, 60 * 24 * 365, null, null, null, false, false, 'lax'));
            }
        }

        return $next($request);
    }
}
