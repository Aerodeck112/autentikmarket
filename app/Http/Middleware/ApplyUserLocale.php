<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zonele fără prefix de limbă (/account, /producer, /staff, /crm):
 * limba = sesiune → preferred_language → cookie → Accept-Language.
 */
class ApplyUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->hasSession() ? $request->session()->get('locale') : null;
        if (! Locales::isSupported($locale)) {
            $locale = Locales::detect($request);
        }
        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
