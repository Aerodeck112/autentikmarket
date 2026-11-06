<?php

namespace App\Http\Controllers\Crm;

use App\Enums\Language;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** „Contul meu” în CRM: fiecare membru al echipei își editează datele și parola. */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('crm.account', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +().-]{6,40}$/'],
            'preferred_language' => ['required', Rule::enum(Language::class)],
        ]);
        $old = $user->only(['email', 'phone']);
        $user->fill(['email' => strtolower($data['email'])] + $data)->save();
        $request->session()->put('locale', $user->preferredLanguage()->value);
        AuditLogger::log('user.self_updated', $user, $old, $user->only(['email', 'phone']));

        return back()->with('success', __('Datele contului au fost salvate.'));
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $request->user()->forceFill(['password' => $request->input('password')])->save();
        AuditLogger::log('user.password_changed', $request->user());

        return back()->with('success', __('Parola a fost schimbată.'));
    }
}
