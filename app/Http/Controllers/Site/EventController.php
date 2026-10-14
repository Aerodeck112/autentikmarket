<?php

namespace App\Http\Controllers\Site;

use App\Enums\EventStatus;
use App\Enums\RaffleStatus;
use App\Enums\RegistrationStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $city = $request->string('city')->toString();
        $month = $request->string('month')->toString(); // YYYY-MM
        $view = $request->string('view')->toString() === 'calendar' ? 'calendar' : 'list';

        $base = Event::published()->where('status', '!=', EventStatus::Cancelled->value)->with('venue');
        $cities = (clone $base)->distinct()->orderBy('city')->pluck('city');

        $monthStart = preg_match('/^\d{4}-\d{2}$/', $month) ? Carbon::parse($month.'-01') : now()->startOfMonth();

        $upcoming = (clone $base)->whereDate('event_date', '>=', now()->toDateString())
            ->when($city, fn ($q) => $q->where('city', $city))
            ->when($view === 'list' && $month, fn ($q) => $q->whereBetween('event_date', [$monthStart->toDateString(), $monthStart->copy()->endOfMonth()->toDateString()]))
            ->orderBy('event_date')->get();

        $calendarEvents = $view === 'calendar'
            ? (clone $base)->when($city, fn ($q) => $q->where('city', $city))
                ->whereBetween('event_date', [$monthStart->copy()->startOfWeek()->toDateString(), $monthStart->copy()->endOfMonth()->endOfWeek()->toDateString()])
                ->get()->groupBy(fn ($e) => $e->event_date->toDateString())
            : collect();

        $past = (clone $base)->where('status', EventStatus::Completed->value)
            ->when($city, fn ($q) => $q->where('city', $city))
            ->orderByDesc('event_date')->limit(6)->get();

        return view('site.events.index', compact('upcoming', 'past', 'cities', 'city', 'view', 'monthStart', 'calendarEvents'));
    }

    public function show(Event $event): View
    {
        abort_if($event->status === EventStatus::Draft, 404);

        $event->load(['venue', 'layout', 'photos']);
        $producers = $event->registrations()->where('status', RegistrationStatus::Approved->value)
            ->with(['producer.category', 'activeReservation.slot'])->get()
            ->filter(fn ($r) => $r->producer && $r->producer->is_public)
            ->sortBy(fn ($r) => $r->producer->brand_name);
        $raffle = $event->raffles()->whereIn('status', [RaffleStatus::Open->value, RaffleStatus::Closed->value, RaffleStatus::Drawn->value])->with('prizes')->first();

        return view('site.events.show', compact('event', 'producers', 'raffle'));
    }

    /** Harta publică: doar statusuri + producătorii aprobați (fără date personale). */
    public function map(Event $event): JsonResponse
    {
        abort_if($event->status === EventStatus::Draft || ! $event->layout, 404);

        $assigned = $event->reservations()->where('status', ReservationStatus::Approved->value)
            ->whereNotNull('active_slot_id')->with('producer:id,brand_name,slug,is_public')->get()->keyBy('event_slot_id');

        return response()->json([
            'layout' => [
                'width_m' => $event->layout->width_m,
                'height_m' => $event->layout->height_m,
                'background_url' => $event->layout->backgroundUrl(),
                'background_opacity' => $event->layout->background_opacity,
                'elements' => $event->layout->elements ?? [],
            ],
            'slots' => $event->slots()->get()->map(function ($s) use ($assigned) {
                $p = $assigned->get($s->id)?->producer;
                $public = in_array($s->status->value, ['available', 'blocked', 'unavailable'], true) ? $s->status->value : 'reserved';

                return [
                    'id' => $s->id, 'code' => $s->code, 'label' => $p && $p->is_public ? null : $s->label,
                    'x' => $s->x, 'y' => $s->y, 'width' => $s->width, 'height' => $s->height, 'rotation' => $s->rotation,
                    'has_electricity' => $s->has_electricity, 'status' => $public,
                    'producer' => $p && $p->is_public ? ['name' => $p->brand_name, 'url' => lroute('producers.show', $p->slug)] : null,
                ];
            })->values(),
        ])->header('Cache-Control', 'public, max-age=15');
    }
}
