<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Accounts\InvitationService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Invitație din CRM: persoana își alege parola din linkul primit pe e-mail (URL semnat, 7 zile, o singură folosire).
 */
class InvitationController extends Controller
{
    public function __construct(protected InvitationService $invitations) {}

    public function show(Request $request, User $user): View|RedirectResponse
    {
        app()->setLocale($user->preferredLanguage()->value);
        if (! $this->invitations->isValidFor($user, (string) $request->query('h'))) {
            return $this->used($user);
        }

        return view('auth.invite', ['user' => $user, 'action' => $request->fullUrl()]);
    }

    public function accept(Request $request, User $user): RedirectResponse
    {
        app()->setLocale($user->preferredLanguage()->value);
        if (! $this->invitations->isValidFor($user, (string) $request->query('h'))) {
            return $this->used($user);
        }
        $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);

        $user->forceFill([
            'password' => $request->input('password'),
            'remember_token' => Str::random(60),
            // A deschis linkul din e-mail: adresa este confirmată.
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
        AuditLogger::log('account.invitation_accepted', $user, [], [], $user->id);

        Auth::logout();
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('locale', $user->preferredLanguage()->value);

        return $user->producer
            ? redirect()->route('producer.profile')->with('success', __('Parola a fost salvată. Bine ai venit în contul de producător!'))
            : redirect()->route('account.card')->with('success', __('Parola a fost salvată. Acesta este cardul tău Autentik.'));
    }

    protected function used(User $user): RedirectResponse
    {
        return redirect()->route($user->preferredLanguage()->value.'.login')
            ->with('error', __('Linkul de invitație a fost deja folosit sau nu mai este valabil. Autentifică-te sau folosește „Am uitat parola”.'));
    }
}
