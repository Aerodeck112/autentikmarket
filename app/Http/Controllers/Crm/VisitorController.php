<?php

namespace App\Http\Controllers\Crm;

use App\Enums\ConsentType;
use App\Enums\Language;
use App\Enums\RegistrationStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Producer;
use App\Models\User;
use App\Services\Accounts\InvitationService;
use App\Services\AuditLogger;
use App\Services\Checkin\CheckinService;
use App\Services\Checkin\InteractionService;
use App\Services\Export\TableExporter;
use App\Services\Search\GlobalSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitorController extends Controller
{
    /** Filtre: TOȚI · RO · HU · 5+ VIZITE · 10+ VIZITE · NOI · RECURENȚI */
    protected function query(Request $request): Builder
    {
        $filter = $request->input('filter', 'all');
        $q = trim((string) $request->input('q'));

        return User::query()
            ->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', ['producer', 'staff', 'organizer', 'admin', 'super_admin']))
            ->whereNull('anonymized_at')
            ->withCount(['checkins', 'reviews', 'raffleEntries', 'rewards'])
            ->withMax('checkins as last_visit', 'checked_in_at')
            ->when($filter === 'ro', fn ($x) => $x->where('preferred_language', 'ro'))
            ->when($filter === 'hu', fn ($x) => $x->where('preferred_language', 'hu'))
            ->when($filter === '5plus', fn ($x) => $x->has('checkins', '>=', 5))
            ->when($filter === '10plus', fn ($x) => $x->has('checkins', '>=', 10))
            ->when($filter === 'new', fn ($x) => $x->where('created_at', '>=', now()->subDays(30)))
            ->when($filter === 'returning', fn ($x) => $x->has('checkins', '>=', 2))
            ->when($request->input('city'), fn ($x, $c) => $x->where('city', $c))
            ->when($request->integer('event'), fn ($x, $e) => $x->whereHas('checkins', fn ($c) => $c->where('event_id', $e)))
            ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('users.first_name', 'like', "%{$q}%")->orWhere('users.last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")->orWhere('member_code', 'like', "%{$q}%")
                ->orWhereRaw(GlobalSearch::fullNameSql().' like ?', ["%{$q}%"])
                ->tap(fn ($w) => GlobalSearch::orWherePhoneDigits($w, $q))));
    }

    public function index(Request $request): View
    {
        $sort = $request->input('sort', 'recent');
        $query = $this->query($request);
        match ($sort) {
            'visits' => $query->orderByDesc('checkins_count'),
            'name' => $query->orderBy('last_name')->orderBy('first_name'),
            default => $query->latest('users.id'),
        };

        $base = User::whereDoesntHave('roles', fn ($r) => $r->whereIn('name', ['producer', 'staff', 'organizer', 'admin', 'super_admin']))->whereNull('anonymized_at');

        return view('crm.visitors.index', [
            'visitors' => tap($query->paginate(30)->withQueryString(), fn ($page) => $page->getCollection()->load([
                'checkins' => fn ($c) => $c->orderBy('checked_in_at'),
                'checkins.event:id,edition,city,event_date,venue_id', 'checkins.event.venue:id,code',
                'interactions.producer:id,brand_name',
            ])),
            'events' => $this->eventOptions(),
            'filter' => $request->input('filter', 'all'),
            'sort' => $sort,
            'counts' => [
                'all' => (clone $base)->count(),
                'ro' => (clone $base)->where('preferred_language', 'ro')->count(),
                'hu' => (clone $base)->where('preferred_language', 'hu')->count(),
            ],
        ]);
    }

    public function export(Request $request, TableExporter $exporter): StreamedResponse
    {
        $rows = $this->query($request)->with(['checkins.event:id,edition,city,event_date,venue_id', 'checkins.event.venue:id,code', 'interactions.producer:id,brand_name'])
            ->orderBy('users.id')->lazy(500)->map(fn (User $u) => [
                $u->first_name, $u->last_name, $u->email, $u->phone, $u->city, strtoupper($u->preferredLanguage()->value), $u->member_code,
                $u->checkins_count, $u->last_visit, self::editionsText($u), self::producersText($u), $u->rewards_count, $u->raffle_entries_count, $u->reviews_count,
                $u->newsletter ? 1 : 0, $u->marketing_consent ? 1 : 0, $u->created_at->format('Y-m-d'),
            ]);

        return $exporter->download('vizitatori-'.now()->format('Ymd'), [
            __('Prenume'), __('Nume'), 'E-mail', __('Telefon'), __('Localitate'), __('Limbă'), __('Cod membru'),
            __('Vizite'), __('Ultima vizită'), __('Ediții'), __('A cumpărat de la'), __('Recompense'), __('Tombole'), __('Recenzii'), 'Newsletter', 'Marketing', __('Data înscrierii'),
        ], $rows, $request->input('format', 'csv'));
    }

    public function show(User $user): View
    {
        $user->loadCount('checkins');

        return view('crm.visitors.show', [
            'visitor' => $user,
            'checkins' => $user->checkins()->with(['event', 'operator'])->latest('checked_in_at')->get(),
            'rewards' => $user->rewards()->with(['rule', 'redemption.operator'])->get(),
            'entries' => $user->raffleEntries()->with('raffle.event')->latest('id')->get(),
            'wins' => $user->raffleWins()->with(['raffle', 'prize'])->get(),
            'reviews' => $user->reviews()->with('producer')->latest('id')->get(),
            'interactions' => $user->interactions()->with(['producer', 'event'])->latest('id')->get(),
            'events' => $this->eventOptions(),
            'producers' => $this->producerOptions(),
            'participants' => $this->participantsMap(),
            'consents' => $user->consents()->latest('id')->get(),
            'messages' => $user->notificationLogs()->latest('id')->limit(20)->get(),
        ]);
    }

    /** Cumpărător adăugat de organizator (ex. la intrarea în târg, fără telefon inteligent). */
    public function create(): View
    {
        return view('crm.visitors.create', [
            'events' => $this->eventOptions(),
            'producers' => $this->producerOptions(),
            'participants' => $this->participantsMap(),
        ]);
    }

    public function store(Request $request, InvitationService $invitations): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40', 'regex:/^[0-9 +().-]{6,40}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'preferred_language' => ['required', Rule::enum(Language::class)],
            'consent_terms' => ['accepted'],
            'newsletter' => ['nullable', 'boolean'],
            'marketing' => ['nullable', 'boolean'],
            'send_invite' => ['nullable', 'boolean'],
            ...self::visitRules(false),
        ], [
            'phone.required_without' => __('Completează cel puțin e-mailul sau telefonul.'),
            'email.unique' => __('Există deja un cont cu acest e-mail. Caută-l în listă.'),
            'consent_terms.accepted' => __('Confirmă că persoana a acceptat termenii și politica de confidențialitate.'),
        ]);

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => isset($data['email']) ? strtolower($data['email']) : null,
                'phone' => $data['phone'] ?? null,
                'city' => $data['city'] ?? null,
                'preferred_language' => $data['preferred_language'],
                'newsletter' => $request->boolean('newsletter'),
                'marketing_consent' => $request->boolean('marketing'),
                // Parolă aleatorie, necunoscută: persoana își alege parola din invitație (sau rămâne doar cu card fizic / cod).
                'password' => Str::random(48),
            ]);
            $user->assignRole('visitor');

            // GDPR: consimțăminte separate, cu sursa „crm” (acord dat pe formular tipărit / verbal, înregistrat de operator).
            $consents = [
                ConsentType::Terms->value => true,
                ConsentType::Privacy->value => true,
                ConsentType::Newsletter->value => $request->boolean('newsletter'),
                ConsentType::Marketing->value => $request->boolean('marketing'),
            ];
            foreach ($consents as $type => $granted) {
                Consent::create([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'type' => $type,
                    'version' => config("autentik.legal_versions.{$type}", '1.0'),
                    'granted' => $granted,
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr('CRM: '.$request->user()->email, 0, 255),
                    'source' => 'crm',
                ]);
            }

            return $user;
        });
        AuditLogger::log('visitor.created_by_admin', $user, [], ['email' => (bool) $user->email]);

        $invited = $request->boolean('send_invite') && $invitations->send($user);
        $visitNote = $request->filled('event_id') ? ' '.$this->recordVisit($user, Event::findOrFail($request->integer('event_id')), (array) $request->input('producer_ids', []), $request->user()) : '';

        return redirect()->route('crm.visitors.show', $user)->with('success', __('Cumpărătorul :n a fost adăugat (cod :c).', ['n' => $user->name, 'c' => $user->member_code])
            .($invited ? ' '.__('Invitația a fost trimisă la :e.', ['e' => $user->email]) : '').$visitNote);
    }

    public function invite(User $user, InvitationService $invitations): RedirectResponse
    {
        if (! $user->email) {
            return back()->with('error', __('Completează întâi adresa de e-mail a cumpărătorului.'));
        }
        if ($user->last_login_at) {
            return back()->with('error', __('Cumpărătorul are deja cont activ. Dacă și-a uitat parola, o poate reseta de pe pagina de autentificare.'));
        }
        $invitations->send($user);

        return back()->with('success', __('Invitația a fost trimisă la :e.', ['e' => $user->email]));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'preferred_language' => ['required', Rule::enum(Language::class)],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['email'] = isset($data['email']) ? strtolower($data['email']) : null;
        if ($data['email'] !== $user->email) {
            $data['email_verified_at'] = null; // adresă nouă = neconfirmată
        }
        $old = $user->only(array_keys($data));
        $user->update($data);
        AuditLogger::log('visitor.updated', $user, $old, $data);

        return back()->with('success', __('Cumpărătorul a fost actualizat.'));
    }

    /** „Înregistrează vizită la târg”: ediția la care a fost + producătorii de la care a cumpărat. */
    public function storeVisit(Request $request, User $user): RedirectResponse
    {
        $request->validate(self::visitRules(true));

        return back()->with('success', $this->recordVisit($user, Event::findOrFail($request->integer('event_id')), (array) $request->input('producer_ids', []), $request->user()));
    }

    protected static function visitRules(bool $eventRequired): array
    {
        return [
            'event_id' => [$eventRequired ? 'required' : 'nullable', 'integer', Rule::exists('events', 'id')->whereNotIn('status', ['draft', 'cancelled'])],
            'producer_ids' => ['nullable', 'array', 'max:100'],
            'producer_ids.*' => ['integer', 'exists:producers,id'],
        ];
    }

    /** Vizita contează ca un check-in (fidelitate, statistici); cumpărăturile ca interacțiuni (recenzii). */
    protected function recordVisit(User $user, Event $event, array $producerIds, User $admin): string
    {
        $at = $event->event_date->copy()->setTimeFromTimeString($event->start_time ?: '10:00');
        $at = $at->isFuture() ? now() : $at;
        try {
            $result = app(CheckinService::class)->checkIn($event, $user, $admin, 'crm', $at);
        } catch (BusinessRuleException $e) {
            return $e->getMessage();
        }

        $added = 0;
        foreach (Producer::whereIn('id', $producerIds)->get() as $producer) {
            try {
                $interaction = app(InteractionService::class)->record($event, $producer, $user, 'crm', false);
                $added += (int) $interaction->wasRecentlyCreated;
            } catch (BusinessRuleException) {
                // ex. propriul stand: ignorat
            }
        }

        $parts = [$result->created
            ? __('Vizita la :e a fost înregistrată (total vizite: :n).', ['e' => $event->shortLabel(), 'n' => $result->visits])
            : __('Vizita la :e era deja înregistrată.', ['e' => $event->shortLabel()])];
        if ($added) {
            $parts[] = __('Cumpărături adăugate: :n.', ['n' => $added]);
        }
        foreach ($result->newRewards as $reward) {
            $parts[] = __('Recompensă nouă: :r.', ['r' => $reward->rule?->title_ro]);
        }

        return implode(' ', $parts);
    }

    /** Edițiile (fără ciorne și anulate), cele mai recente primele. */
    protected function eventOptions(): array
    {
        return Event::whereNotIn('status', ['draft', 'cancelled'])->orderByDesc('event_date')->limit(200)->get()
            ->mapWithKeys(fn (Event $e) => [$e->id => $e->shortLabel()])->all();
    }

    protected function producerOptions(): array
    {
        return Producer::approved()->orderBy('brand_name')->pluck('brand_name', 'id')->all();
    }

    /** event_id → producătorii aprobați la acea ediție (evidențiați în formular). */
    protected function participantsMap(): array
    {
        return EventRegistration::where('status', RegistrationStatus::Approved->value)
            ->get(['event_id', 'producer_id'])->groupBy('event_id')
            ->map(fn ($rows) => $rows->pluck('producer_id')->values())->all();
    }

    public static function editionsText(User $u): string
    {
        return $u->checkins->sortBy('checked_in_at')->map(fn ($c) => self::editionTag($c->event))->filter()->implode(', ');
    }

    public static function editionTag(?Event $e): ?string
    {
        if (! $e) {
            return null;
        }
        $place = $e->venue?->code ?: mb_substr((string) $e->city, 0, 3);

        return trim($place.' '.($e->edition ? 'ed. '.$e->edition : $e->event_date?->format('d.m.Y')));
    }

    public static function producersText(User $u): string
    {
        return $u->interactions->pluck('producer.brand_name')->filter()->unique()->sort()->implode(', ');
    }
}
