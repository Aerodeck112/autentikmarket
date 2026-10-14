<?php

namespace App\Http\Controllers\Producer;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventSlot;
use App\Models\StandReservation;
use App\Services\Reservations\StandReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Flow participare: 1. verificare date → 2. confirmare → 3. necesități → 4. hartă →
 * 5. alegere stand (hold) → 6. solicitare → 7. aprobare admin.
 */
class EventController extends Controller
{
    public function __construct(protected StandReservationService $reservations) {}

    public function index(Request $request): View
    {
        $producer = $request->user()->producer;
        $regs = $producer->registrations()->with('activeReservation.slot')->get()->keyBy('event_id');

        return view('producer.events', [
            'producer' => $producer,
            'upcoming' => Event::upcoming()->with('venue')->get(),
            'registrations' => $regs,
            'history' => $producer->registrations()->with(['event', 'reservations' => fn ($q) => $q->where('status', ReservationStatus::Approved->value)->with('slot')])
                ->whereHas('event', fn ($q) => $q->whereDate('event_date', '<', now()->toDateString()))
                ->get()->sortByDesc(fn ($r) => $r->event->event_date),
        ]);
    }

    public function show(Request $request, Event $event): View
    {
        abort_if(in_array($event->status, [EventStatus::Draft], true), 404);
        $producer = $request->user()->producer;
        $this->reservations->expireHolds($event->id);

        $registration = EventRegistration::where('event_id', $event->id)->where('producer_id', $producer->id)->first();
        $reservation = $registration
            ? StandReservation::where('event_id', $event->id)->where('producer_id', $producer->id)->latest('id')->with('slot')->first()
            : null;

        return view('producer.event-show', [
            'event' => $event->load(['venue', 'layout']),
            'producer' => $producer,
            'registration' => $registration,
            'reservation' => $reservation,
            'annex' => $registration ? $producer->annexes()->where('event_id', $event->id)->latest('id')->first() : null,
        ]);
    }

    public function confirm(Request $request, Event $event): RedirectResponse
    {
        $producer = $request->user()->producer;
        if (! $producer->isApproved()) {
            return back()->with('error', __('Doar producătorii aprobați pot participa.'));
        }
        if (! $event->registrationsOpen()) {
            return back()->with('error', __('Înscrierile pentru acest târg nu sunt deschise.'));
        }
        $reg = EventRegistration::firstOrCreate(['event_id' => $event->id, 'producer_id' => $producer->id], ['status' => RegistrationStatus::Draft]);
        if ($reg->status === RegistrationStatus::Cancelled) {
            $reg->status = RegistrationStatus::Draft;
        }
        $reg->data_confirmed_at = now();
        $reg->save();

        return redirect()->to(route('producer.events.show', $event).'#harta')->with('success', __('Datele au fost confirmate. Alege standul pe hartă.'));
    }

    public function map(Request $request, Event $event): JsonResponse
    {
        abort_unless($event->layout, 404);
        $producer = $request->user()->producer;
        $this->reservations->expireHolds($event->id);

        $mine = StandReservation::where('event_id', $event->id)->where('producer_id', $producer->id)
            ->whereNotNull('active_slot_id')->value('event_slot_id');

        return response()->json([
            'layout' => [
                'width_m' => $event->layout->width_m,
                'height_m' => $event->layout->height_m,
                'background_url' => $event->layout->backgroundUrl(),
                'background_opacity' => $event->layout->background_opacity,
            ],
            'slots' => $event->slots()->get()->map(fn (EventSlot $s) => [
                'id' => $s->id, 'code' => $s->code, 'label' => $s->label,
                'x' => $s->x, 'y' => $s->y, 'width' => $s->width, 'height' => $s->height, 'rotation' => $s->rotation,
                'status' => $s->status->value,
                'mine' => $mine === $s->id,
                'has_electricity' => $s->has_electricity,
                'price' => $s->price,
                'price_label' => money_ron($s->price),
                'dimensions' => $s->dimensions(),
                'features' => $s->features ?? [],
            ])->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function hold(Request $request, Event $event, EventSlot $slot): JsonResponse
    {
        $producer = $request->user()->producer;
        $reg = EventRegistration::where('event_id', $event->id)->where('producer_id', $producer->id)->first();
        if (! $reg?->data_confirmed_at) {
            return response()->json(['message' => __('Confirmă întâi datele firmei (pasul 1).')], 422);
        }

        try {
            $r = $this->reservations->hold($event, $slot, $producer);
        } catch (BusinessRuleException $e) {
            return response()->json(['message' => $e->getMessage(), 'reason' => $e->reason], 409);
        }

        return response()->json([
            'reservation_id' => $r->id,
            'expires_at' => $r->hold_expires_at?->toIso8601String(),
            'redirect' => route('producer.events.show', $event).'#solicitare',
        ]);
    }

    public function request(Request $request, Event $event, StandReservation $reservation): RedirectResponse
    {
        abort_unless($reservation->event_id === $event->id, 404);
        $data = $request->validate([
            'needs_electricity' => ['nullable', 'boolean'],
            'needs_table' => ['nullable', 'boolean'],
            'needs_tent' => ['nullable', 'boolean'],
            'products_note' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['accepted'],
        ]);
        $this->reservations->request($reservation, $request->user()->producer, $data);

        return redirect()->route('producer.events.show', $event)->with('success', __('Cererea a fost trimisă. Te anunțăm când este aprobată.'));
    }

    public function cancel(Request $request, Event $event, StandReservation $reservation): RedirectResponse
    {
        $producer = $request->user()->producer;
        abort_unless($reservation->event_id === $event->id && $reservation->producer_id === $producer->id, 403);
        if (! in_array($reservation->status, [ReservationStatus::Hold, ReservationStatus::Requested], true)) {
            return back()->with('error', __('O rezervare aprobată se anulează doar prin organizatori.'));
        }
        $this->reservations->cancel($reservation, $request->user(), 'producer');

        return back()->with('success', __('Rezervarea a fost anulată.'));
    }
}
