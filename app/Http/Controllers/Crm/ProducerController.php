<?php

namespace App\Http\Controllers\Crm;

use App\Enums\DocumentType;
use App\Enums\Language;
use App\Enums\ProducerStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Producer;
use App\Models\ProducerCategory;
use App\Models\ProducerDocument;
use App\Models\User;
use App\Services\Accounts\InvitationService;
use App\Services\AuditLogger;
use App\Services\Export\TableExporter;
use App\Services\Producers\ProducerWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProducerController extends Controller
{
    public function __construct(protected ProducerWorkflow $workflow) {}

    /** Filtre obligatorii: TOȚI · ROMÂNĂ · MAGHIARĂ · ÎN VERIFICARE · APROBAȚI · DOCUMENTE LIPSĂ */
    protected function query(Request $request): Builder
    {
        $filter = $request->input('filter', 'all');
        $q = trim((string) $request->input('q'));

        return Producer::query()->with(['category', 'user'])
            ->withCount([
                'registrations as participations_count' => fn ($r) => $r->where('status', RegistrationStatus::Approved->value),
                'documents', 'contracts',
            ])
            ->withAvg('approvedReviews as rating', 'rating')
            ->withMax(['registrations as last_participation' => fn ($r) => $r->where('event_registrations.status', RegistrationStatus::Approved->value)->join('events', 'events.id', '=', 'event_registrations.event_id')->whereDate('events.event_date', '<', now()->toDateString())], 'events.event_date')
            ->when($filter === 'ro', fn ($x) => $x->where('preferred_language', 'ro'))
            ->when($filter === 'hu', fn ($x) => $x->where('preferred_language', 'hu'))
            ->when($filter === 'review', fn ($x) => $x->whereIn('status', [ProducerStatus::DocumentsSubmitted->value, ProducerStatus::InReview->value]))
            ->when($filter === 'approved', fn ($x) => $x->where('status', ProducerStatus::Approved->value))
            ->when($filter === 'missing_docs', fn ($x) => $x->where(fn ($w) => $w->doesntHave('documents')->orWhereHas('documentRequests', fn ($d) => $d->whereNull('fulfilled_at'))))
            ->when($filter === 'new', fn ($x) => $x->whereIn('status', [ProducerStatus::New->value, ProducerStatus::Incomplete->value]))
            ->when($request->input('category'), fn ($x, $c) => $x->where('producer_category_id', $c))
            ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('brand_name', 'like', "%{$q}%")->orWhere('legal_name', 'like', "%{$q}%")
                ->orWhere('representative_name', 'like', "%{$q}%")->orWhere('cui', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%")));
    }

    public function index(Request $request): View
    {
        $producers = $this->query($request)->orderByRaw("case status when 'documents_submitted' then 0 when 'in_review' then 1 else 2 end")->orderBy('brand_name')->paginate(30)->withQueryString();
        $nextEventIds = Event::upcoming()->pluck('id');

        return view('crm.producers.index', [
            'producers' => $producers,
            'filter' => $request->input('filter', 'all'),
            'categories' => ProducerCategory::orderBy('sort_order')->get(),
            'nextRegs' => EventRegistration::whereIn('event_id', $nextEventIds)->whereIn('producer_id', $producers->pluck('id'))
                ->with('event')->get()->groupBy('producer_id'),
            'counts' => [
                'all' => Producer::count(),
                'ro' => Producer::where('preferred_language', 'ro')->count(),
                'hu' => Producer::where('preferred_language', 'hu')->count(),
                'review' => Producer::whereIn('status', [ProducerStatus::DocumentsSubmitted->value, ProducerStatus::InReview->value])->count(),
                'approved' => Producer::where('status', ProducerStatus::Approved->value)->count(),
            ],
        ]);
    }

    public function export(Request $request, TableExporter $exporter): StreamedResponse
    {
        $rows = $this->query($request)->orderBy('brand_name')->lazy(200)->map(fn (Producer $p) => [
            $p->brand_name, $p->legal_name, $p->cui, $p->representative_name, $p->phone, $p->email, $p->city, $p->county,
            strtoupper($p->preferredLanguage()->value), $p->category?->name_ro, $p->status->value, $p->participations_count,
            $p->last_participation, $p->contracts_count, $p->documents_count, $p->rating ? round($p->rating, 1) : '',
        ]);

        return $exporter->download('producatori-'.now()->format('Ymd'), [
            __('Producător'), __('Denumire juridică'), 'CUI', __('Reprezentant'), __('Telefon'), 'E-mail', __('Localitate'), __('Județ'),
            __('Limbă'), __('Categorie'), __('Status'), __('Nr. participări'), __('Ultima participare'), __('Contracte'), __('Documente'), __('Rating'),
        ], $rows, $request->input('format', 'csv'));
    }

    public function show(Producer $producer): View
    {
        $producer->load(['category', 'user', 'documents.reviewer', 'documentRequests.requester', 'statusHistory.user', 'contracts', 'annexes.event', 'gallery']);

        return view('crm.producers.show', [
            'producer' => $producer,
            'registrations' => $producer->registrations()->with(['event', 'activeReservation.slot'])->latest('id')->get(),
            'reviews' => $producer->reviews()->with('user')->latest('id')->limit(10)->get(),
            'missing' => $this->workflow->missingFields($producer),
            'interactions' => $producer->interactions()->count(),
        ]);
    }

    /** Câmpurile fișei de producător (aceleași la adăugare și la editare). */
    protected function rules(): array
    {
        return [
            'brand_name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'cui' => ['nullable', 'string', 'max:30'],
            'trade_register_no' => ['nullable', 'string', 'max:50'],
            'representative_name' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'county' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'iban' => ['nullable', 'string', 'max:40'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            // Admin poate modifica manual limba producătorului.
            'preferred_language' => ['required', Rule::enum(Language::class)],
            'producer_category_id' => ['nullable', 'exists:producer_categories,id'],
            'description_ro' => ['nullable', 'string', 'max:3000'],
            'description_hu' => ['nullable', 'string', 'max:3000'],
            'story_ro' => ['nullable', 'string', 'max:6000'],
            'story_hu' => ['nullable', 'string', 'max:6000'],
            'products_ro' => ['nullable', 'string', 'max:3000'],
            'products_hu' => ['nullable', 'string', 'max:3000'],
            'shop_url' => ['nullable', 'url', 'max:255'],
            'is_public' => ['nullable', 'boolean'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** Producător adăugat de organizator (ex. după o discuție la târg). */
    public function create(): View
    {
        return view('crm.producers.create', ['producer' => new Producer(['preferred_language' => 'ro', 'is_public' => true]), 'categories' => ProducerCategory::orderBy('sort_order')->get()]);
    }

    public function store(Request $request, InvitationService $invitations): RedirectResponse
    {
        // E-mailul nu este obligatoriu. Dacă este completat (și invitația e bifată), producătorul primește cont + link de invitație.
        $data = $request->validate(array_merge($this->rules(), self::accountRules(), [
            'approve' => ['nullable', 'boolean'],
        ]));
        $email = isset($data['email']) ? strtolower($data['email']) : null;
        $withAccount = $email && $request->boolean('create_account');
        $existing = $withAccount ? User::where('email', $email)->first() : null;
        if ($existing?->producer) {
            return back()->withInput()->withErrors(['email' => __('Contul cu acest e-mail aparține deja producătorului :p.', ['p' => $existing->producer->brand_name])]);
        }

        $producer = DB::transaction(function () use ($data, $request, $email) {
            $fields = collect($data)->except(['create_account', 'contact_first_name', 'contact_last_name', 'approve'])->all();
            $producer = new Producer($fields);
            $producer->email = $email;
            $producer->is_public = $request->boolean('is_public');
            $producer->save();

            return $producer;
        });
        $account = $withAccount ? $this->attachAccount($producer, $data, $invitations) : null;
        AuditLogger::log('producer.created_by_admin', $producer, [], ['account' => (bool) $account, 'linked_existing' => (bool) $existing]);

        if ($request->boolean('approve') && $request->user()->can('producers.approve')) {
            $this->workflow->changeStatus($producer->fresh(), ProducerStatus::Approved, $request->user(), __('Adăugat și aprobat de organizator.'));
        }

        $message = __('Producătorul :p a fost adăugat.', ['p' => $producer->brand_name]);
        if ($account) {
            $message .= ' '.$account;
        } elseif (! $email) {
            $message .= ' '.__('Nu are e-mail: invitația pe platformă se trimite automat când completezi adresa în fișă.');
        }

        return redirect()->route('crm.producers.show', $producer)->with('success', $message);
    }

    /** Câmpurile pentru cont (persoana de contact e opțională: implicit reprezentantul sau denumirea). */
    protected static function accountRules(): array
    {
        return [
            'create_account' => ['nullable', 'boolean'],
            'contact_first_name' => ['nullable', 'string', 'max:100'],
            'contact_last_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Creează contul producătorului pe adresa din fișă și trimite invitația (sau îl leagă de un cont existent
     * cu același e-mail, de ex. un cumpărător). Returnează mesajul pentru organizator sau null dacă nu s-a putut.
     */
    protected function attachAccount(Producer $producer, array $data, InvitationService $invitations): ?string
    {
        $email = strtolower((string) $producer->email);
        if ($producer->user_id || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $existing = User::where('email', $email)->first();
        if ($existing?->producer) {
            return __('Contul nu a fost creat: adresa :e aparține deja producătorului :p.', ['e' => $email, 'p' => $existing->producer->brand_name]);
        }
        [$first, $last] = self::contactName($producer, $data);
        $user = DB::transaction(function () use ($producer, $existing, $email, $first, $last) {
            $user = $existing ?? User::create([
                'first_name' => $first, 'last_name' => $last,
                'email' => $email, 'phone' => $producer->phone, 'city' => $producer->city,
                'preferred_language' => $producer->preferredLanguage()->value,
                // Parolă aleatorie, necunoscută: persoana își alege parola din linkul de invitație.
                'password' => Str::random(48),
            ]);
            $user->assignRole('producer');
            $producer->user_id = $user->id;
            $producer->representative_name ??= $user->name;
            $producer->save();

            return $user;
        });
        if ($existing) {
            return __('A fost legat de contul existent :e.', ['e' => $existing->email]);
        }

        return $invitations->send($user, $producer)
            ? __('Invitația de creare a parolei a fost trimisă la :e.', ['e' => $user->email])
            : null;
    }

    /** @return array{0: string, 1: string} prenume, nume pentru cont */
    protected static function contactName(Producer $producer, array $data): array
    {
        $first = trim((string) ($data['contact_first_name'] ?? ''));
        $last = trim((string) ($data['contact_last_name'] ?? ''));
        if ($first !== '' || $last !== '') {
            return [$first !== '' ? $first : $last, $first !== '' ? $last : ''];
        }
        $full = trim((string) ($producer->representative_name ?: $producer->brand_name));
        $parts = preg_split('/\s+/', $full, 2);

        return [Str::limit($parts[0], 100, ''), Str::limit($parts[1] ?? '', 100, '')];
    }

    /** Cont pentru un producător fără cont, sau retrimiterea invitației dacă nu a intrat niciodată. */
    public function invite(Request $request, Producer $producer, InvitationService $invitations): RedirectResponse
    {
        $user = $producer->user;
        if ($user && $user->last_login_at) {
            return back()->with('error', __('Producătorul are deja cont activ. Dacă și-a uitat parola, o poate reseta de pe pagina de autentificare.'));
        }
        if ($user) {
            $invitations->send($user, $producer);

            return back()->with('success', __('Invitația de creare a parolei a fost trimisă la :e.', ['e' => $user->email]));
        }
        $data = $request->validate(self::accountRules());
        if (! filter_var((string) $producer->email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', __('Completează întâi adresa de e-mail în fișa producătorului.'));
        }
        $message = $this->attachAccount($producer, $data, $invitations);
        $ok = $producer->fresh()->user_id !== null;

        return back()->with($ok ? 'success' : 'error', $message ?? __('Invitația nu a putut fi trimisă.'));
    }

    public function edit(Producer $producer): View
    {
        return view('crm.producers.edit', ['producer' => $producer, 'categories' => ProducerCategory::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Producer $producer, InvitationService $invitations): RedirectResponse
    {
        $data = $request->validate(array_merge($this->rules(), self::accountRules()));
        $data = collect($data)->except(array_keys(self::accountRules()))->all();
        $data['is_public'] = $request->boolean('is_public');
        $old = $producer->only(['preferred_language', 'brand_name', 'shop_url']);
        if ($data['brand_name'] !== $producer->brand_name) {
            $data['slug'] = Producer::uniqueSlug($data['brand_name'], $producer->id);
        }
        $producer->update($data);
        AuditLogger::log('producer.updated', $producer, $old, $producer->only(['preferred_language', 'brand_name', 'shop_url']));

        $message = __('Producătorul a fost actualizat.');
        // E-mail completat pentru un producător fără cont → cont + invitație pe platformă (dacă e bifat).
        if (! $producer->user_id && $producer->email && $request->boolean('create_account')) {
            $account = $this->attachAccount($producer, $request->validate(self::accountRules()), $invitations);
            $message .= $account ? ' '.$account : '';
        }

        return redirect()->route('crm.producers.show', $producer)->with('success', $message);
    }

    public function status(Request $request, Producer $producer): RedirectResponse
    {
        $request->validate([
            'status' => ['required', Rule::enum(ProducerStatus::class)],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('status') === ProducerStatus::Rejected->value)],
        ]);
        $this->workflow->changeStatus($producer, ProducerStatus::from($request->input('status')), $request->user(), $request->input('note'));

        return back()->with('success', __('Statusul producătorului a fost actualizat.'));
    }

    public function requestDocuments(Request $request, Producer $producer): RedirectResponse
    {
        $data = $request->validate([
            'types' => ['required', 'array', 'min:1'],
            'types.*' => [Rule::enum(DocumentType::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->workflow->requestDocuments($producer, $data['types'], $data['note'] ?? null, $request->user());

        return back()->with('success', __('Solicitarea a fost trimisă producătorului, în limba lui.'));
    }

    public function reviewDocument(Request $request, Producer $producer, ProducerDocument $document): RedirectResponse
    {
        abort_unless($document->producer_id === $producer->id, 404);
        $data = $request->validate(['status' => ['required', 'in:accepted,rejected,pending'], 'review_note' => ['nullable', 'string', 'max:1000']]);
        $old = $document->status;
        $document->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        AuditLogger::log('producer_document.reviewed', $document, ['status' => $old], $data);
        if ($producer->status === ProducerStatus::DocumentsSubmitted) {
            $this->workflow->changeStatus($producer, ProducerStatus::InReview, $request->user(), __('Verificare documente începută.'));
        }

        return back()->with('success', __('Documentul a fost marcat.'));
    }
}
