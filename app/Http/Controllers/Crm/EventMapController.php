<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\Producer;
use App\Models\StandReservation;
use App\Services\Reservations\StandReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Harta evenimentului în CRM: statusuri live, blocare, alocare directă. */
class EventMapController extends Controller
{
    public function __construct(protected StandReservationService $reservations) {}

    public function show(Event $event): View
    {
        return view('crm.events.map', [
            'event' => $event->load('layout'),
            'producers' => Producer::approved()->orderBy('brand_name')->get(['id', 'brand_name']),
        ]);
    }

    public function data(Event $event): JsonResponse
    {
        abort_unless($event->layout, 404);
        $this->reservations->expireHolds($event->id);
        $active = StandReservation::where('event_id', $event->id)->whereNotNull('active_slot_id')->with('producer:id,brand_name,preferred_language')->get()->keyBy('event_slot_id');

        return response()->json([
            'layout' => [
                'width_m' => $event->layout->width_m, 'height_m' => $event->layout->height_m,
                'background_url' => $event->layout->backgroundUrl(), 'background_opacity' => $event->layout->background_opacity,
            ],
            'slots' => $event->slots()->get()->map(function (EventSlot $s) use ($active) {
                $r = $active->get($s->id);

                return [
                    'id' => $s->id, 'code' => $s->code, 'label' => $r?->producer?->brand_name ? mb_substr($r->producer->brand_name, 0, 14) : $s->label,
                    'x' => $s->x, 'y' => $s->y, 'width' => $s->width, 'height' => $s->height, 'rotation' => $s->rotation,
                    'status' => $s->status->value, 'has_electricity' => $s->has_electricity, 'price_label' => money_ron($s->price),
                    'dimensions' => $s->dimensions(),
                    'reservation' => $r ? ['id' => $r->id, 'status' => $r->status->value, 'producer' => $r->producer?->brand_name, 'language' => $r->producer?->preferredLanguage()->value, 'expires' => $r->hold_expires_at?->format('H:i')] : null,
                ];
            })->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function block(Request $request, Event $event, EventSlot $slot): RedirectResponse|JsonResponse
    {
        abort_unless($slot->event_id === $event->id, 404);
        $this->reservations->setBlocked($slot, $request->boolean('blocked'), $request->user());

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', __('Standul a fost actualizat.'));
    }

    public function assign(Request $request, Event $event, EventSlot $slot): RedirectResponse|JsonResponse
    {
        abort_unless($slot->event_id === $event->id, 404);
        $request->validate(['producer_id' => ['required', 'exists:producers,id']]);
        $producer = Producer::findOrFail($request->integer('producer_id'));
        $this->reservations->assign($event, $slot, $producer, $request->user());

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', __('Standul a fost alocat.'));
    }
}
