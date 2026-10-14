<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleRedirectController extends Controller
{
    /** `/` → `/ro` sau `/hu` (cookie → utilizator → Accept-Language). */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route(Locales::detect($request).'.home');
    }

    /** Comutator de limbă pentru zonele fără prefix (cont, producător, staff, CRM). */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $request->session()->put('locale', $locale);
        Cookie::queue(Cookie::make('locale', $locale, 60 * 24 * 365, null, null, null, false, false, 'lax'));

        $back = url()->previous();
        // Pe paginile publice localizate trimitem utilizatorul la varianta în limba aleasă.
        $path = parse_url($back, PHP_URL_PATH) ?? '/';
        if (preg_match('#^/(ro|hu)(/|$)#', $path)) {
            return redirect()->route($locale.'.home');
        }

        return redirect()->to($back ?: route($locale.'.home'));
    }
}
