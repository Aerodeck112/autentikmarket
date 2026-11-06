<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        // Câmpul „email” acceptă adresa de e-mail SAU numărul de telefon.
        $data = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);
        $login = trim($data['email']);

        if (Phone::looksLikePhone($login)) {
            // Un număr poate aparține mai multor conturi (ex. familie): intră contul a cărui parolă se potrivește.
            $user = User::findByPhone($login)->where('is_active', true)
                ->first(fn (User $u) => Hash::check($data['password'], (string) $u->password));
            if (! $user) {
                throw ValidationException::withMessages(['email' => __('Datele de autentificare nu sunt corecte.')]);
            }
            Auth::login($user, $request->boolean('remember'));
        } elseif (! Auth::attempt(['email' => strtolower($login), 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('Datele de autentificare nu sunt corecte.')]);
        }

        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->put('locale', $user->preferredLanguage()->value);

        return redirect()->intended(route('dashboard.redirect'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $locale = app()->getLocale();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($locale.'.home');
    }
}
