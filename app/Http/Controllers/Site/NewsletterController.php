<?php

namespace App\Http\Controllers\Site;

use App\Enums\ConsentType;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\NewsletterSubscriber;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Newsletter cu double opt-in. Consimțământul este înregistrat separat (GDPR).
 */
class NewsletterController extends Controller
{
    public function store(Request $request, NotificationService $notifications): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return back()->with('success', __('Verifică-ți e-mailul pentru a confirma abonarea.'));
        }
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'consent' => ['accepted'],
        ]);

        $sub = NewsletterSubscriber::firstOrNew(['email' => strtolower($data['email'])]);
        $sub->preferred_language = app()->getLocale();
        $sub->token ??= Str::random(48);
        $sub->ip_address = $request->ip();
        $sub->unsubscribed_at = null;
        $sub->user_id ??= $request->user()?->id;
        $sub->save();

        if (! $sub->confirmed_at) {
            $notifications->send('NEWSLETTER_CONFIRM', $sub, [], [
                'action_url' => route('newsletter.confirm', $sub->token),
                'action_label_ro' => 'Confirm abonarea',
                'action_label_hu' => 'Feliratkozás megerősítése',
                'dedupe_key' => 'newsletter-confirm:'.$sub->id.':'.now()->format('YmdH'),
            ]);
        }

        return back()->with('success', __('Verifică-ți e-mailul pentru a confirma abonarea.'));
    }

    public function confirm(Request $request, string $token): RedirectResponse
    {
        $sub = NewsletterSubscriber::where('token', $token)->firstOrFail();
        if (! $sub->confirmed_at) {
            $sub->update(['confirmed_at' => now()]);
            Consent::create([
                'user_id' => $sub->user_id, 'email' => $sub->email, 'type' => ConsentType::Newsletter,
                'version' => config('autentik.legal_versions.newsletter'), 'granted' => true,
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255), 'source' => 'double_opt_in',
            ]);
        }

        return redirect()->route($sub->preferredLanguage()->value.'.home')->with('success', __('Abonarea a fost confirmată. Mulțumim!'));
    }

    public function unsubscribe(Request $request, string $token): RedirectResponse
    {
        $sub = NewsletterSubscriber::where('token', $token)->firstOrFail();
        $sub->update(['unsubscribed_at' => now()]);
        Consent::create([
            'user_id' => $sub->user_id, 'email' => $sub->email, 'type' => ConsentType::Newsletter,
            'version' => config('autentik.legal_versions.newsletter'), 'granted' => false, 'revoked_at' => now(),
            'ip_address' => $request->ip(), 'source' => 'unsubscribe_link',
        ]);

        return redirect()->route($sub->preferredLanguage()->value.'.home')->with('success', __('Te-ai dezabonat de la newsletter.'));
    }
}
