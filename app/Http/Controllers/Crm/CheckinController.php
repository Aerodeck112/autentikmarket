<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\Checkin\CheckinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckinController extends Controller
{
    public function index(Request $request): View
    {
        $events = Event::published()->orderByDesc('event_date')->limit(30)->get();
        $event = $events->firstWhere('id', (int) $request->input('event')) ?? $events->first(fn ($e) => $e->isToday()) ?? $events->first();

        return view('crm.checkins.index', [
            'events' => $events,
            'event' => $event,
            'checkins' => $event ? $event->checkins()->with(['user', 'operator'])->latest('checked_in_at')->paginate(50)->withQueryString() : null,
        ]);
    }

    public function store(Request $request, CheckinService $service): RedirectResponse
    {
        $data = $request->validate(['event_id' => ['required', 'exists:events,id'], 'member_code' => ['required', 'string', 'max:20']]);
        $user = User::where('member_code', strtoupper(trim($data['member_code'])))->first();
        if (! $user) {
            return back()->with('error', __('Cod de membru inexistent.'));
        }
        $result = $service->checkIn(Event::findOrFail($data['event_id']), $user, $request->user(), 'manual');

        return back()->with($result->created ? 'success' : 'info', $result->created ? __('Check-in reușit!').' '.$user->name : __('Vizitatorul are deja check-in la acest eveniment.'));
    }
}
