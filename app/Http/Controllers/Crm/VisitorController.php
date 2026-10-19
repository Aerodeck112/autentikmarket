<?php

namespace App\Http\Controllers\Crm;

use App\Enums\ConsentType;
use App\Enums\Language;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\User;
use App\Services\Accounts\InvitationService;
use App\Services\AuditLogger;
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
            'visitors' => $query->paginate(30)->withQueryString(),
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
        $rows = $this->query($request)->orderBy('users.id')->lazy(500)->map(fn (User $u) => [
            $u->first_name, $u->last_name, $u->email, $u->phone, $u->city, strtoupper($u->preferredLanguage()->value), $u->member_code,
            $u->checkins_count, $u->last_visit, $u->rewards_count, $u->raffle_entries_count, $u->reviews_count,
            $u->newsletter ? 1 : 0, $u->marketing_consent ? 1 : 0, $u->created_at->format('Y-m-d'),
        ]);

        return $exporter->download('vizitatori-'.now()->format('Ymd'), [
            __('Prenume'), __('Nume'), 'E-mail', __('Telefon'), __('Localitate'), __('Limbă'), __('Cod membru'),
            __('Vizite'), __('Ultima vizită'), __('Recompense'), __('Tombole'), __('Recenzii'), 'Newsletter', 'Marketing', __('Data înscrierii'),
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
            'consents' => $user->consents()->latest('id')->get(),
            'messages' => $user->notificationLogs()->latest('id')->limit(20)->get(),
        ]);
    }

    /** Cumpărător adăugat de organizator (ex. la intrarea în târg, fără telefon inteligent). */
    public function create(): View
    {
        return view('crm.visitors.create');
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

        return redirect()->route('crm.visitors.show', $user)->with('success', __('Cumpărătorul :n a fost adăugat (cod :c).', ['n' => $user->name, 'c' => $user->member_code])
            .($invited ? ' '.__('Invitația a fost trimisă la :e.', ['e' => $user->email]) : ''));
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
}
