<?php

namespace App\Services\Campaigns;

use App\Enums\ConsentType;
use App\Enums\ProducerStatus;
use App\Enums\RegistrationStatus;
use App\Mail\CampaignMail;
use App\Models\Consent;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\NewsletterSubscriber;
use App\Models\Producer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\TemplateRenderer;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Campanii e-mail trimise din CRM.
 *
 * - Cumpărătorii primesc campanii DOAR cu acord (newsletter sau marketing) — GDPR.
 * - Producătorii (contacte de afaceri) primesc campaniile lor, cu posibilitatea de a renunța.
 * - Fiecare destinatar primește textul în limba lui și un link de dezabonare.
 * - Trimiterea se face treptat, din cron, cu limita pe oră din Setări (hostingul limitează e-mailurile).
 */
class CampaignService
{
    public const AUDIENCES = [
        'newsletter' => ['buyers', false],
        'marketing' => ['buyers', false],
        'buyers_consent' => ['buyers', false],
        'edition_buyers' => ['buyers', true],
        'producers_approved' => ['producers', false],
        'producers_all' => ['producers', false],
        'edition_producers' => ['producers', true],
        'team' => ['team', false],
    ];

    public function __construct(protected TemplateRenderer $renderer) {}

    /** @return array<string, string> */
    public static function audienceLabels(): array
    {
        return [
            'newsletter' => __('Abonații la newsletter (cumpărători cu acord + abonați de pe site)'),
            'marketing' => __('Cumpărătorii cu acord de marketing'),
            'buyers_consent' => __('Toți cumpărătorii cu acord (newsletter sau marketing)'),
            'edition_buyers' => __('Cumpărătorii unei ediții (cu acord)'),
            'producers_approved' => __('Producătorii aprobați'),
            'producers_all' => __('Toți producătorii (fără cei respinși)'),
            'edition_producers' => __('Producătorii acceptați la o ediție'),
            'team' => __('Echipa (staff, organizatori, administratori) — pentru verificare'),
        ];
    }

    public static function needsEvent(string $audience): bool
    {
        return self::AUDIENCES[$audience][1] ?? false;
    }

    /**
     * Destinatarii unici (după e-mail) pentru publicul ales.
     *
     * @return Collection<string, array{email: string, name: string, language: string, user_id: ?int, producer_id: ?int, newsletter_subscriber_id: ?int}>
     */
    public function resolve(string $audience, ?int $eventId = null): Collection
    {
        $out = collect();
        $add = function (?string $email, string $name, string $lang, array $ids) use ($out) {
            $email = strtolower(trim((string) $email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $out->has($email)) {
                return;
            }
            $out->put($email, ['email' => $email, 'name' => $name, 'language' => in_array($lang, ['ro', 'hu'], true) ? $lang : 'ro',
                'user_id' => $ids['user_id'] ?? null, 'producer_id' => $ids['producer_id'] ?? null, 'newsletter_subscriber_id' => $ids['newsletter_subscriber_id'] ?? null]);
        };
        $users = fn () => User::query()->whereNull('anonymized_at')->where('is_active', true)->whereNotNull('email');

        switch ($audience) {
            case 'newsletter':
            case 'marketing':
            case 'buyers_consent':
            case 'edition_buyers':
                $q = $users()->where(fn ($w) => match ($audience) {
                    'newsletter' => $w->where('newsletter', true),
                    'marketing' => $w->where('marketing_consent', true),
                    default => $w->where('newsletter', true)->orWhere('marketing_consent', true),
                });
                if ($audience === 'edition_buyers') {
                    $q->whereHas('checkins', fn ($c) => $c->where('event_id', $eventId));
                }
                foreach ($q->orderBy('id')->cursor() as $u) {
                    $add($u->email, $u->first_name, $u->preferredLanguage()->value, ['user_id' => $u->id]);
                }
                if (in_array($audience, ['newsletter', 'buyers_consent'], true)) {
                    foreach (NewsletterSubscriber::whereNotNull('confirmed_at')->whereNull('unsubscribed_at')->orderBy('id')->cursor() as $s) {
                        $add($s->email, '', $s->preferredLanguage()->value, ['newsletter_subscriber_id' => $s->id, 'user_id' => $s->user_id]);
                    }
                }
                break;

            case 'producers_approved':
            case 'producers_all':
            case 'edition_producers':
                $q = Producer::query()->with('user')->where('campaigns_opt_out', false);
                match ($audience) {
                    'producers_approved' => $q->where('status', ProducerStatus::Approved->value),
                    'producers_all' => $q->where('status', '!=', ProducerStatus::Rejected->value),
                    'edition_producers' => $q->whereHas('registrations', fn ($r) => $r->where('event_id', $eventId)->where('status', RegistrationStatus::Approved->value)),
                };
                foreach ($q->orderBy('id')->cursor() as $p) {
                    $add($p->email ?: $p->user?->email, $p->representative_name ?: $p->brand_name, $p->preferredLanguage()->value, ['producer_id' => $p->id, 'user_id' => $p->user_id]);
                }
                break;

            case 'team':
                foreach ($users()->whereHas('roles', fn ($r) => $r->whereIn('name', ['staff', 'organizer', 'admin', 'super_admin']))->cursor() as $u) {
                    $add($u->email, $u->first_name, $u->preferredLanguage()->value, ['user_id' => $u->id]);
                }
                break;
        }

        return $out;
    }

    /** Pornește trimiterea: instantaneul destinatarilor se face acum; trimiterea efectivă, treptat, din cron. */
    public function start(EmailCampaign $campaign, User $by): int
    {
        if (! in_array($campaign->status, ['draft'], true)) {
            return 0;
        }
        $recipients = $this->resolve($campaign->audience, $campaign->event_id);
        DB::transaction(function () use ($campaign, $recipients) {
            foreach ($recipients->chunk(500) as $chunk) {
                EmailCampaignRecipient::insertOrIgnore($chunk->map(fn ($r) => $r + [
                    'email_campaign_id' => $campaign->id, 'status' => 'queued', 'created_at' => now(), 'updated_at' => now(),
                ])->values()->all());
            }
            $campaign->forceFill([
                'status' => $recipients->isEmpty() ? 'sent' : 'sending',
                'recipients_count' => $recipients->count(), 'started_at' => now(),
                'finished_at' => $recipients->isEmpty() ? now() : null,
            ])->save();
        });
        AuditLogger::log('campaign.started', $campaign, [], ['audience' => $campaign->audience, 'recipients' => $recipients->count()], $by->id);

        return $recipients->count();
    }

    /**
     * Pas din cron: trimite următoarele e-mailuri, în limita pe oră (și cel mult $seconds secunde).
     *
     * @return int numărul de e-mailuri trimise
     */
    public function tick(int $seconds = 20): int
    {
        $perHour = max(1, (int) Settings::get('campaigns.hourly_limit', 200));
        $sentLastHour = EmailCampaignRecipient::where('status', 'sent')->where('sent_at', '>=', now()->subHour())->count();
        $budget = min($perHour - $sentLastHour, (int) ceil($perHour / 60) * 2);
        if ($budget <= 0) {
            return 0;
        }
        $deadline = microtime(true) + $seconds;
        $sent = 0;

        foreach (EmailCampaign::where('status', 'sending')->orderBy('started_at')->get() as $campaign) {
            $batch = $campaign->recipients()->where('status', 'queued')->orderBy('id')->limit($budget - $sent)->get();
            foreach ($batch as $r) {
                if (microtime(true) > $deadline) {
                    break 2;
                }
                $sent += $this->deliver($campaign, $r) ? 1 : 0;
            }
            $this->refreshCounts($campaign);
            if ($sent >= $budget) {
                break;
            }
        }

        return $sent;
    }

    public function deliver(EmailCampaign $campaign, EmailCampaignRecipient $r): bool
    {
        $r->increment('attempts');
        try {
            [$subject, $html] = $this->render($campaign, $r->language, (string) $r->name, $this->unsubscribeUrl($r));
            Mail::to($r->email)->send(new CampaignMail($subject, $html, $r->language, $this->unsubscribeUrl($r)));
            $r->forceFill(['status' => 'sent', 'sent_at' => now(), 'error' => null])->save();

            return true;
        } catch (Throwable $e) {
            // După 3 încercări adresa rămâne „eșuată”; altfel se reîncearcă la următorul pas din cron.
            $r->forceFill(['status' => $r->attempts >= 3 ? 'failed' : 'queued', 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            return false;
        }
    }

    public function refreshCounts(EmailCampaign $campaign): void
    {
        $counts = $campaign->recipients()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $campaign->forceFill(['sent_count' => (int) ($counts['sent'] ?? 0), 'failed_count' => (int) ($counts['failed'] ?? 0)]);
        if ($campaign->status === 'sending' && (int) ($counts['queued'] ?? 0) === 0) {
            $campaign->forceFill(['status' => 'sent', 'finished_at' => now()]);
        }
        $campaign->save();
    }

    /** @return array{0: string, 1: string} subiect, HTML complet al e-mailului */
    public function render(EmailCampaign $campaign, string $lang, string $name, ?string $unsubscribeUrl): array
    {
        $data = ['name' => $name !== '' ? $name : ($lang === 'hu' ? 'Kedves barátunk' : 'Dragă prietene'), 'app_name' => config('app.name'), 'app_url' => config('app.url')];
        $subject = $this->renderer->renderText($campaign->{"subject_{$lang}"} ?: $campaign->subject_ro, $data);
        $body = $this->renderer->renderHtml($campaign->{"body_{$lang}"} ?: $campaign->body_ro, $data);

        $previous = App::getLocale();
        App::setLocale($lang);
        try {
            $html = view('mail.templated', [
                'subject' => $subject, 'bodyHtml' => $body, 'actionUrl' => null, 'actionLabel' => null, 'lang' => $lang,
                'unsubscribeUrl' => $unsubscribeUrl,
            ])->render();
        } finally {
            App::setLocale($previous);
        }

        return [$subject, $html];
    }

    public function unsubscribeUrl(EmailCampaignRecipient $r): string
    {
        return URL::signedRoute('campaigns.unsubscribe', ['recipient' => $r->id]);
    }

    /** Dezabonare din linkul e-mailului: retrage acordurile (cumpărător / abonat) sau renunțarea producătorului. */
    public function unsubscribe(EmailCampaignRecipient $r, ?string $ip = null): void
    {
        DB::transaction(function () use ($r, $ip) {
            if ($r->producer_id) {
                Producer::whereKey($r->producer_id)->update(['campaigns_opt_out' => true]);
            }
            $user = $r->user_id ? User::find($r->user_id) : null;
            if ($user && ! $r->producer_id) {
                foreach (['newsletter' => ConsentType::Newsletter, 'marketing_consent' => ConsentType::Marketing] as $field => $type) {
                    if ($user->$field) {
                        Consent::create([
                            'user_id' => $user->id, 'email' => $user->email, 'type' => $type,
                            'version' => config('autentik.legal_versions.'.$type->value), 'granted' => false,
                            'revoked_at' => now(), 'ip_address' => $ip, 'source' => 'campaign_unsubscribe',
                        ]);
                        $user->$field = false;
                    }
                }
                $user->save();
            }
            if (! $r->producer_id) {
                NewsletterSubscriber::where('email', $r->email)->whereNull('unsubscribed_at')->update(['unsubscribed_at' => now()]);
            }
            $r->forceFill(['unsubscribed_at' => now()])->save();
        });
    }
}
