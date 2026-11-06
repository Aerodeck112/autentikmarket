<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Mail\CampaignMail;
use App\Models\EmailCampaign;
use App\Models\Event;
use App\Services\AuditLogger;
use App\Services\Campaigns\CampaignService;
use App\Support\MailSettings;
use App\Support\QueueRunner;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** CRM → Campanii e-mail: creare (RO/HU), public țintă, previzualizare, test, trimitere treptată, statistici. */
class CampaignController extends Controller
{
    public function __construct(protected CampaignService $campaigns) {}

    public function index(): View
    {
        return view('crm.campaigns.index', [
            'campaigns' => EmailCampaign::with(['creator', 'event'])->latest('id')->paginate(25),
            'hourlyLimit' => (int) Settings::get('campaigns.hourly_limit', 200),
        ]);
    }

    public function create(Request $request): View
    {
        $campaign = new EmailCampaign(['audience' => 'newsletter']);
        if ($from = EmailCampaign::find($request->integer('copy'))) {
            $campaign->fill($from->only(['subject_ro', 'subject_hu', 'body_ro', 'body_hu', 'audience', 'event_id']) + ['name' => $from->name.' ('.__('copie').')']);
        }

        return view('crm.campaigns.form', ['campaign' => $campaign, 'events' => $this->eventOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campaign = new EmailCampaign($this->validated($request));
        $campaign->created_by = $request->user()->id;
        $campaign->save();

        return redirect()->route('crm.campaigns.show', $campaign)->with('success', __('Campania a fost salvată ca ciornă. Verific-o, trimite-ți un test, apoi pornește trimiterea.'));
    }

    public function show(EmailCampaign $campaign): View
    {
        $lang = request('lang') === 'hu' ? 'hu' : 'ro';
        [$subject, $html] = $this->campaigns->render($campaign, $lang, request()->user()->first_name, '#');

        return view('crm.campaigns.show', [
            'campaign' => $campaign,
            'lang' => $lang,
            'previewSubject' => $subject,
            'previewHtml' => $html,
            'audienceCount' => $campaign->isDraft() ? $this->campaigns->resolve($campaign->audience, $campaign->event_id)->count() : $campaign->recipients_count,
            'failed' => $campaign->recipients()->where('status', 'failed')->latest('id')->limit(50)->get(),
            'unsubscribed' => $campaign->recipients()->whereNotNull('unsubscribed_at')->count(),
            'byLanguage' => $campaign->isDraft() ? $this->campaigns->resolve($campaign->audience, $campaign->event_id)->countBy('language')
                : $campaign->recipients()->selectRaw('language, count(*) as n')->groupBy('language')->pluck('n', 'language'),
            'hourlyLimit' => (int) Settings::get('campaigns.hourly_limit', 200),
            'queue' => QueueRunner::lastRun(),
        ]);
    }

    public function edit(EmailCampaign $campaign): View|RedirectResponse
    {
        if (! $campaign->isDraft()) {
            return redirect()->route('crm.campaigns.show', $campaign)->with('error', __('Doar o ciornă se poate modifica. Folosește „Copiază” pentru o campanie nouă.'));
        }

        return view('crm.campaigns.form', ['campaign' => $campaign, 'events' => $this->eventOptions()]);
    }

    public function update(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->isDraft(), 403);
        $campaign->update($this->validated($request));

        return redirect()->route('crm.campaigns.show', $campaign)->with('success', __('Campania a fost actualizată.'));
    }

    public function destroy(EmailCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->isDraft(), 403);
        $campaign->delete();

        return redirect()->route('crm.campaigns.index')->with('success', __('Ciorna a fost ștearsă.'));
    }

    /** Test: ambele limbi, imediat, la adresa celui care testează. */
    public function test(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        $email = $request->user()->email;
        if (! $email) {
            return back()->with('error', __('Contul tău nu are e-mail.'));
        }
        try {
            foreach (['ro', 'hu'] as $lang) {
                [$subject, $html] = $this->campaigns->render($campaign, $lang, $request->user()->first_name, route('crm.campaigns.show', $campaign));
                Mail::to($email)->send(new CampaignMail('[TEST '.strtoupper($lang).'] '.$subject, $html, $lang, route('crm.campaigns.show', $campaign)));
            }
        } catch (Throwable $e) {
            return back()->with('error', MailSettings::explain($e));
        }

        return back()->with('success', __('Am trimis testul (RO și HU) la :e.', ['e' => $email]));
    }

    public function start(Request $request, EmailCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->isDraft(), 403);
        $n = $this->campaigns->start($campaign, $request->user());

        return back()->with('success', $n
            ? __('Trimiterea a pornit către :n destinatari. E-mailurile pleacă treptat (maximum :h pe oră), prin cron.', ['n' => $n, 'h' => (int) Settings::get('campaigns.hourly_limit', 200)])
            : __('Publicul ales nu are niciun destinatar; campania nu a trimis nimic.'));
    }

    public function pause(EmailCampaign $campaign): RedirectResponse
    {
        if ($campaign->status === 'sending') {
            $campaign->forceFill(['status' => 'paused'])->save();
        } elseif ($campaign->status === 'paused') {
            $campaign->forceFill(['status' => 'sending'])->save();
        }
        AuditLogger::log('campaign.'.$campaign->status, $campaign);

        return back()->with('success', $campaign->status === 'paused' ? __('Trimiterea a fost oprită temporar.') : __('Trimiterea a fost reluată.'));
    }

    public function cancel(EmailCampaign $campaign): RedirectResponse
    {
        if (in_array($campaign->status, ['sending', 'paused'], true)) {
            $campaign->recipients()->where('status', 'queued')->delete();
            $campaign->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
            $this->campaigns->refreshCounts($campaign);
            AuditLogger::log('campaign.cancelled', $campaign);
        }

        return back()->with('success', __('Campania a fost anulată; mesajele netrimise au fost scoase din coadă.'));
    }

    /** Trimite acum un lot (fără să aștepte cron-ul) — util pe găzduiri cu cron rar. */
    public function sendNow(EmailCampaign $campaign): RedirectResponse
    {
        $n = $this->campaigns->tick(25);

        return back()->with('success', __('Trimise acum: :n.', ['n' => $n]));
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['hourly_limit' => ['required', 'integer', 'min:10', 'max:5000']]);
        Settings::set('campaigns.hourly_limit', (string) $data['hourly_limit'], 'campaigns');

        return back()->with('success', __('Limita a fost salvată.'));
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject_ro' => ['required', 'string', 'max:200'],
            'subject_hu' => ['required', 'string', 'max:200'],
            'body_ro' => ['required', 'string', 'max:20000'],
            'body_hu' => ['required', 'string', 'max:20000'],
            'audience' => ['required', Rule::in(array_keys(CampaignService::AUDIENCES))],
            'event_id' => ['nullable', 'integer', 'exists:events,id', Rule::requiredIf(fn () => CampaignService::needsEvent((string) $request->input('audience')))],
        ], ['event_id.required' => __('Alege ediția pentru acest public.')]);
        if (! CampaignService::needsEvent($data['audience'])) {
            $data['event_id'] = null;
        }

        return $data;
    }

    /** @return array<int, string> */
    protected function eventOptions(): array
    {
        return Event::whereNotIn('status', ['draft', 'cancelled'])->orderByDesc('event_date')->limit(200)->get()
            ->mapWithKeys(fn (Event $e) => [$e->id => $e->shortLabel()])->all();
    }
}
