<?php

namespace App\Http\Controllers\Crm;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\StandReservation;
use App\Services\AuditLogger;
use App\Services\Documents\DocumentService;
use App\Services\Reservations\StandReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function __construct(protected StandReservationService $service) {}

    public function index(Request $request, Event $event): View
    {
        $this->service->expireHolds($event->id);
        $filter = $request->input('status');

        return view('crm.events.reservations', [
            'event' => $event,
            'filter' => $filter,
            'reservations' => StandReservation::where('event_id', $event->id)
                ->when($filter, fn ($q) => $q->where('status', $filter), fn ($q) => $q->whereNotNull('active_slot_id'))
                ->with(['producer.category', 'slot', 'registration'])->latest('id')->paginate(50)->withQueryString(),
            'registrations' => EventRegistration::where('event_id', $event->id)->where('status', RegistrationStatus::Approved->value)
                ->with(['producer', 'activeReservation.slot'])->get()->sortBy('producer.brand_name'),
        ]);
    }

    public function approve(Request $request, StandReservation $reservation): RedirectResponse
    {
        $this->service->approve($reservation, $request->user(), $request->input('note'));

        return back()->with('success', __('Rezervarea a fost aprobată. Producătorul a fost notificat în limba lui.'));
    }

    public function reject(Request $request, StandReservation $reservation): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->service->reject($reservation, $request->user(), $request->input('reason'));

        return back()->with('success', __('Rezervarea a fost respinsă.'));
    }

    public function cancel(Request $request, StandReservation $reservation): RedirectResponse
    {
        $this->service->cancel($reservation, $request->user(), $request->input('reason'));

        return back()->with('success', __('Rezervarea a fost anulată, standul este din nou disponibil.'));
    }

    public function attendance(Request $request, EventRegistration $registration): RedirectResponse
    {
        $request->validate(['attended' => ['required', 'in:0,1']]);
        $old = $registration->attended;
        $registration->update(['attended' => $request->boolean('attended')]);
        AuditLogger::log('registration.attendance', $registration, ['attended' => $old], ['attended' => $registration->attended]);

        return back()->with('success', __('Prezența a fost actualizată.'));
    }

    public function annex(Request $request, StandReservation $reservation, DocumentService $documents): RedirectResponse
    {
        if ($reservation->status->value !== 'approved') {
            return back()->with('error', __('Anexa se generează doar pentru rezervări aprobate.'));
        }
        $annex = $documents->generateAnnex($reservation, $request->user());

        return back()->with('success', __('Anexa :n a fost generată.', ['n' => $annex->number]));
    }
}
