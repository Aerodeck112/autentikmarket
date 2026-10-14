<?php

namespace App\Http\Controllers\Account;

use App\Enums\ConsentType;
use App\Enums\Language;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +().-]{6,40}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'preferred_language' => ['required', Rule::enum(Language::class)],
        ]);

        $emailChanged = strtolower($data['email']) !== $user->email;
        $user->fill($data + ['email' => strtolower($data['email'])]);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();
        $request->session()->put('locale', $user->preferredLanguage()->value);
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('success', __('Profilul a fost actualizat.'));
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $request->user()->forceFill(['password' => $request->password])->save();

        return back()->with('success', __('Parola a fost schimbată.'));
    }

    /** Consimțăminte separate (newsletter / marketing) — fiecare modificare este înregistrată. */
    public function consents(Request $request): RedirectResponse
    {
        $user = $request->user();
        $map = ['newsletter' => ConsentType::Newsletter, 'marketing_consent' => ConsentType::Marketing];
        foreach ($map as $field => $type) {
            $new = $request->boolean($field);
            if ((bool) $user->$field !== $new) {
                Consent::create([
                    'user_id' => $user->id, 'email' => $user->email, 'type' => $type,
                    'version' => config('autentik.legal_versions.'.$type->value), 'granted' => $new,
                    'revoked_at' => $new ? null : now(), 'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 255), 'source' => 'profile',
                ]);
                $user->$field = $new;
            }
        }
        $user->save();

        return back()->with('success', __('Preferințele de comunicare au fost salvate.'));
    }

    /**
     * Ștergerea contului = ANONIMIZARE (statisticile agregate se păstrează, datele personale nu).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        if ($user->producer || $user->canAccessCrm()) {
            return back()->with('error', __('Conturile de producător sau de staff se închid prin solicitare la organizatori.'));
        }

        DB::transaction(function () use ($user) {
            AuditLogger::log('user.anonymized', $user, [], ['by' => 'self']);
            $user->forceFill([
                'first_name' => 'Utilizator',
                'last_name' => 'șters',
                'email' => 'deleted-'.$user->id.'-'.Str::random(6).'@invalid.local',
                'phone' => null,
                'city' => null,
                'county' => null,
                'password' => Hash::make(Str::random(40)),
                'newsletter' => false,
                'marketing_consent' => false,
                'is_active' => false,
                'anonymized_at' => now(),
                'remember_token' => null,
            ])->save();
            $user->regenerateCardToken();
            $user->reviews()->update(['body' => null, 'photo_path' => null, 'product_name' => null]);
        });

        $locale = app()->getLocale();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($locale.'.home')->with('success', __('Contul tău a fost șters.'));
    }
}
