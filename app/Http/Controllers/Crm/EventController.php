<?php

namespace App\Http\Controllers\Crm;

use App\Enums\EventStatus;
use App\Enums\ProducerStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Media;
use App\Models\Producer;
use App\Models\Venue;
use App\Services\Events\EventLifecycleService;
use App\Services\Events\LayoutSnapshotService;
use App\Services\Events\PostEventWorkflow;
use App\Services\Export\TableExporter;
use App\Services\Stats\StatisticsService;
use App\Services\Tasks\TaskGenerator;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    public function __construct(protected EventLifecycleService $lifecycle) {}

    public function index(Request $request): View
    {
        $status = $request->input('status');
        $events = Event::with('venue')->withCount(['checkins', 'approvedRegistrations'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->input('city'), fn ($q, $c) => $q->where('city', $c))
            ->orderByDesc('event_date')->paginate(25)->withQueryString();

        return view('crm.events.index', ['events' => $events, 'status' => $status, 'cities' => Event::distinct()->pluck('city')]);
    }

    public function create(Request $request): View
    {
        return view('crm.events.form', [
            'event' => new Event(['start_time' => '08:00', 'end_time' => '15:00']),
            'venues' => Venue::where('active', true)->orderBy('name')->get(),
            'past' => $request->boolean('past'),
            'producers' => $this->producerOptions(),
            'present' => [],
        ]);
    }

    /** @return array<int, string> producătorii (în afară de cei respinși), pentru ediții trecute */
    protected function producerOptions(): array
    {
        return Producer::where('status', '!=', ProducerStatus::Rejected->value)->orderBy('brand_name')->pluck('brand_name', 'id')->all();
    }

    /** Ediție din trecut, intrată în CRM pe fluxul normal → arhivă (fără e-mailurile de după târg). */
    public function archive(Request $request, Event $event): RedirectResponse
    {
        $this->lifecycle->archive($event, $request->user());
        Cache::forget('home.v1.ro');
        Cache::forget('home.v1.hu');

        return back()->with('success', __('Ediția a fost trecută în arhivă (încheiată), fără e-mailuri către producători sau vizitatori.'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        // Ediție trecută: numărul automat (următorul după cel mai mare) ar fi greșit → se scrie de mână.
        if (empty($data['edition']) && Carbon::parse($data['event_date'])->lt(today())) {
            return back()->withInput()->withErrors(['edition' => __('Pentru o ediție trecută scrie numărul ediției.')]);
        }
        if ($request->hasFile('cover')) {
            $data['cover_path'] = Uploads::storeImage($request->file('cover'), 'events');
        }
        unset($data['cover'], $data['present_ids'], $data['present_form']);
        if (empty($data['edition'])) {
            unset($data['edition']); // se numerotează automat
        }
        $event = $this->lifecycle->create($data, $request->user());
        Cache::forget('home.v1.ro');
        Cache::forget('home.v1.hu');

        if (EventLifecycleService::isPast($event)) {
            if ($request->boolean('present_form')) {
                $this->lifecycle->syncPastParticipants($event, $request->input('present_ids', []), $request->user());
            }

            return redirect()->route('crm.events.show', $event)->with('success', __('Ediția trecută a fost adăugată în arhivă (încheiată). Nu s-au trimis e-mailuri și nu s-au generat task-uri. Vizitele cumpărătorilor le poți înregistra din fișa fiecăruia.'));
        }

        return redirect()->route('crm.events.show', $event)->with('success', __('Evenimentul a fost creat. Harta și checklist-ul au fost generate automat.'));
    }

    public function show(Event $event, StatisticsService $stats): View
    {
        $event->load(['venue', 'layout', 'photos', 'raffles', 'blogPosts']);

        return view('crm.events.show', [
            'event' => $event,
            'stats' => $stats->eventStats($event),
            'tasks' => $event->tasks()->orderBy('due_date')->get(),
            'transitions' => EventLifecycleService::TRANSITIONS[$event->status->value] ?? [],
        ]);
    }

    public function edit(Event $event): View
    {
        return view('crm.events.form', [
            'event' => $event,
            'venues' => Venue::orderBy('name')->get(),
            'past' => false,
            'producers' => $this->producerOptions(),
            'present' => $event->registrations()->where('status', RegistrationStatus::Approved->value)->pluck('producer_id')->all(),
        ]);
    }

    public function update(Request $request, Event $event, TaskGenerator $tasks): RedirectResponse
    {
        $data = $this->validated($request, $event);
        if ($request->hasFile('cover')) {
            if ($event->cover_path) {
                Storage::disk('public')->delete($event->cover_path);
            }
            $data['cover_path'] = Uploads::storeImage($request->file('cover'), 'events');
        }
        unset($data['cover'], $data['present_ids'], $data['present_form']);
        $data['edition'] ??= $event->edition ?? Event::nextEdition($data['venue_id'] ?? $event->venue_id, $data['city'] ?? $event->city);
        $dateChanged = $data['event_date'] !== $event->event_date->toDateString();
        $event->update($data);
        if ($dateChanged && ! EventLifecycleService::isPast($event)) {
            $tasks->rescheduleForEvent($event->fresh());
        }
        if ($request->boolean('present_form')) {
            $this->lifecycle->syncPastParticipants($event, $request->input('present_ids', []), $request->user());
        }
        Cache::forget('home.v1.ro');
        Cache::forget('home.v1.hu');

        return redirect()->route('crm.events.show', $event)->with('success', __('Evenimentul a fost actualizat.'));
    }

    public function status(Request $request, Event $event): RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::enum(EventStatus::class)]]);
        $this->lifecycle->changeStatus($event, EventStatus::from($request->input('status')), $request->user());
        Cache::forget('home.v1.ro');
        Cache::forget('home.v1.hu');

        return back()->with('success', __('Statusul evenimentului a fost actualizat.'));
    }

    public function snapshot(Request $request, Event $event, LayoutSnapshotService $snapshots): RedirectResponse
    {
        $master = $event->venue?->masterLayout;
        if (! $master) {
            return back()->with('error', __('Locația nu are un layout master.'));
        }
        $snapshots->snapshot($event, $master);

        return back()->with('success', __('Harta evenimentului a fost resincronizată cu layout-ul master.'));
    }

    public function uploadPhotos(Request $request, Event $event): RedirectResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'max:30'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('autentik.uploads.image_max_kb')],
        ]);
        $order = $event->photos()->max('sort_order') ?? 0;
        foreach ($request->file('photos') as $file) {
            $path = Uploads::storeImage($file, "events/{$event->id}/photos");
            $size = @getimagesize(Storage::disk('public')->path($path)) ?: [null, null];
            $event->photos()->create([
                'collection' => 'event_photos', 'disk' => 'public', 'path' => $path, 'mime' => $file->getMimeType(),
                'size' => $file->getSize(), 'width' => $size[0], 'height' => $size[1], 'uploaded_by' => $request->user()->id,
                'sort_order' => ++$order,
            ]);
        }
        Cache::forget('home.v1.ro');
        Cache::forget('home.v1.hu');

        return back()->with('success', __('Fotografiile au fost încărcate.'));
    }

    public function deletePhoto(Event $event, Media $media): RedirectResponse
    {
        abort_unless($media->mediable_type === $event->getMorphClass() && (int) $media->mediable_id === $event->id, 404);
        Storage::disk($media->disk)->delete(array_filter([$media->path, $media->thumb_path]));
        $media->delete();

        return back()->with('success', __('Fotografia a fost ștearsă.'));
    }

    public function runPostEvent(Event $event, PostEventWorkflow $workflow): RedirectResponse
    {
        if ($event->status !== EventStatus::Completed) {
            return back()->with('error', __('Workflow-ul post-eveniment rulează doar pentru evenimente încheiate.'));
        }
        if ($event->archived_at) {
            return back()->with('error', __('Ediție din arhivă (adăugată retroactiv): nu se trimit mesajele de după târg.'));
        }
        $r = $workflow->run($event);

        return back()->with('success', __('Workflow post-eveniment: :p producători, :v vizitatori, :r cereri de recenzie, :t task-uri.', [
            'p' => $r['producers'], 'v' => $r['visitors'], 'r' => $r['review_requests'], 't' => $r['tasks'],
        ]));
    }

    public function export(Request $request, Event $event, string $type, TableExporter $exporter): StreamedResponse
    {
        $format = $request->input('format', 'csv');
        if ($type === 'visitors') {
            $rows = $event->checkins()->with(['user', 'operator'])->orderBy('checked_in_at')->lazy(500)->map(fn ($c) => [
                $c->user->first_name, $c->user->last_name, $c->user->email, $c->user->phone, $c->user->city,
                strtoupper($c->user->preferredLanguage()->value), $c->user->member_code,
                $c->checked_in_at->format('Y-m-d H:i'), $c->method, $c->operator?->name,
            ]);

            return $exporter->download("participanti-{$event->slug}", [
                __('Prenume'), __('Nume'), 'E-mail', __('Telefon'), __('Localitate'), __('Limbă'), __('Cod membru'), __('Check-in'), __('Metodă'), __('Operator'),
            ], $rows, $format);
        }

        $rows = $event->registrations()->with(['producer.category', 'activeReservation.slot'])->lazy(200)->map(fn ($r) => [
            $r->producer->brand_name, $r->producer->legal_name, $r->producer->representative_name, $r->producer->phone, $r->producer->email,
            $r->producer->city, strtoupper($r->producer->preferredLanguage()->value), $r->producer->category?->name_ro,
            $r->status->value, $r->activeReservation?->slot?->code, $r->needs_electricity ? 1 : 0, $r->needs_table ? 1 : 0, $r->needs_tent ? 1 : 0,
            $r->attended === null ? '' : ($r->attended ? 1 : 0),
        ]);

        return $exporter->download("producatori-{$event->slug}", [
            __('Producător'), __('Denumire juridică'), __('Reprezentant'), __('Telefon'), 'E-mail', __('Localitate'), __('Limbă'), __('Categorie'),
            __('Status'), __('Stand'), __('Electricitate'), __('Masă'), __('Cort'), __('Prezent'),
        ], $rows, $format);
    }

    protected function validated(Request $request, ?Event $event = null): array
    {
        return $request->validate([
            'title_ro' => ['required', 'string', 'max:200'],
            'title_hu' => ['required', 'string', 'max:200'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'city' => ['required', 'string', 'max:120'],
            'venue_name' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'description_ro' => ['nullable', 'string', 'max:10000'],
            'description_hu' => ['nullable', 'string', 'max:10000'],
            'program_ro' => ['nullable', 'string', 'max:5000'],
            'program_hu' => ['nullable', 'string', 'max:5000'],
            'registration_start' => ['nullable', 'date'],
            'registration_end' => ['nullable', 'date', 'after_or_equal:registration_start'],
            'max_producers' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'edition' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'present_form' => ['nullable', 'boolean'],
            'present_ids' => ['nullable', 'array', 'max:500'],
            'present_ids.*' => ['integer', 'exists:producers,id'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('autentik.uploads.image_max_kb')],
        ]);
    }
}
