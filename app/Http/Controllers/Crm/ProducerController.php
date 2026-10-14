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
use App\Services\AuditLogger;
use App\Services\Export\TableExporter;
use App\Services\Producers\ProducerWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function edit(Producer $producer): View
    {
        return view('crm.producers.edit', ['producer' => $producer, 'categories' => ProducerCategory::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Producer $producer): RedirectResponse
    {
        $data = $request->validate([
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
        ]);
        $data['is_public'] = $request->boolean('is_public');
        $old = $producer->only(['preferred_language', 'brand_name', 'shop_url']);
        if ($data['brand_name'] !== $producer->brand_name) {
            $data['slug'] = Producer::uniqueSlug($data['brand_name'], $producer->id);
        }
        $producer->update($data);
        AuditLogger::log('producer.updated', $producer, $old, $producer->only(['preferred_language', 'brand_name', 'shop_url']));

        return redirect()->route('crm.producers.show', $producer)->with('success', __('Producătorul a fost actualizat.'));
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
