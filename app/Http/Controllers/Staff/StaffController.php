<?php

namespace App\Http\Controllers\Staff;

use App\Enums\EventStatus;
use App\Enums\RewardStatus;
use App\Enums\WinnerStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Producer;
use App\Models\RaffleWinner;
use App\Models\Reward;
use App\Models\User;
use App\Services\Checkin\CheckinService;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Raffles\RaffleService;
use App\Services\Search\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STAFF PWA (mobil): scanner QR, check-in, căutare client, recompense, câștigători tombolă, producători.
 */
class StaffController extends Controller
{
    public function __construct(
        protected CheckinService $checkins,
        protected LoyaltyService $loyalty,
        protected RaffleService $raffles,
    ) {}

    protected function events()
    {
        return Event::whereIn('status', [EventStatus::RegistrationsOpen->value, EventStatus::RegistrationsClosed->value, EventStatus::Active->value, EventStatus::Planning->value])
            ->whereBetween('event_date', [now()->subDay()->toDateString(), now()->addDays(1)->toDateString()])
            ->orderBy('event_date')->get();
    }

    protected function currentEvent(Request $request): ?Event
    {
        $events = $this->events();
        $id = (int) $request->input('event', $request->session()->get('staff_event'));

        return $events->firstWhere('id', $id) ?? $events->first(fn ($e) => $e->isToday()) ?? $events->first();
    }

    public function index(Request $request): View
    {
        $event = $this->currentEvent($request);
        if ($event) {
            $request->session()->put('staff_event', $event->id);
        }

        return view('staff.index', [
            'events' => $this->events(),
            'event' => $event,
            'count' => $event ? $event->checkins()->count() : 0,
        ]);
    }

    /** Deep-link din QR (camera nativă a telefonului) → fișa vizitatorului. */
    public function scanLink(Request $request, string $token): RedirectResponse
    {
        $user = User::where('card_token', $token)->firstOrFail();
        $event = $this->currentEvent($request);

        return redirect()->route('staff.visitor', ['user' => $user, 'event' => $event?->id, 'autocheckin' => 1]);
    }

    public function checkin(Request $request): JsonResponse
    {
        $request->validate(['event_id' => ['required', 'integer'], 'code' => ['nullable', 'string', 'max:500'], 'user_id' => ['nullable', 'integer']]);
        $event = $this->events()->firstWhere('id', (int) $request->input('event_id'));
        if (! $event) {
            return response()->json(['message' => __('Evenimentul nu este disponibil pentru check-in.')], 422);
        }

        $manual = $request->filled('user_id');
        $user = $manual ? User::find($request->integer('user_id')) : $this->checkins->findUserByToken((string) $request->input('code'));
        if (! $user) {
            return response()->json(['message' => __('Card necunoscut. Încearcă check-in manual.')], 404);
        }

        try {
            $result = $this->checkins->checkIn($event, $user, $request->user(), $manual ? 'manual' : 'qr');
        } catch (BusinessRuleException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'created' => $result->created,
            'message' => $result->created ? __('Check-in reușit!') : __('Vizitatorul are deja check-in la acest eveniment.'),
            'visitor' => $this->visitorPayload($user, $event),
            'new_rewards' => $result->newRewards->map(fn ($r) => $r->rule->t('title'))->values(),
            'count' => $event->checkins()->count(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $users = User::query()->whereNull('anonymized_at')
            ->where(fn ($w) => $w->where('member_code', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)
                ->orWhere('last_name', 'like', $like)->orWhere('first_name', 'like', $like)
                ->orWhereRaw(GlobalSearch::fullNameSql().' like ?', [$like]))
            ->withCount('checkins')->limit(15)->get();

        return response()->json(['results' => $users->map(fn (User $u) => [
            'id' => $u->id, 'name' => $u->name, 'member_code' => $u->member_code, 'city' => $u->city,
            'language' => $u->preferredLanguage()->value, 'visits' => $u->checkins_count,
            'url' => route('staff.visitor', $u),
        ])]);
    }

    public function visitor(Request $request, User $user): View
    {
        $event = $this->currentEvent($request);
        $autoResult = null;
        if ($request->boolean('autocheckin') && $event) {
            try {
                $autoResult = $this->checkins->checkIn($event, $user, $request->user(), 'qr');
            } catch (BusinessRuleException $e) {
                session()->flash('error', $e->getMessage());
            }
        }

        return view('staff.visitor', [
            'visitor' => $user->loadCount('checkins'),
            'event' => $event,
            'autoResult' => $autoResult,
            'checkedIn' => $event ? $user->checkins()->where('event_id', $event->id)->exists() : false,
            'rewards' => $user->rewards()->with(['rule', 'redemption'])->latest('earned_at')->get(),
            'wins' => RaffleWinner::with(['raffle.event', 'prize', 'draw'])->where('user_id', $user->id)
                ->whereHas('draw', fn ($q) => $q->where('status', 'valid'))->latest('id')->get(),
        ]);
    }

    public function redeem(Request $request, Reward $reward): RedirectResponse
    {
        $this->loyalty->redeem($reward, $request->user(), $this->currentEvent($request));

        return back()->with('success', __('Cadoul a fost marcat ca ridicat.'));
    }

    public function claim(Request $request, RaffleWinner $winner): RedirectResponse
    {
        $this->raffles->claim($winner, $request->user());

        return back()->with('success', __('Premiul a fost marcat ca ridicat.'));
    }

    public function producers(Request $request): View
    {
        $q = trim((string) $request->input('q'));
        $event = $this->currentEvent($request);
        $producers = Producer::approved()->with('category')
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('brand_name', 'like', "%{$q}%")->orWhere('representative_name', 'like', "%{$q}%")))
            ->when($event && ! $q, fn ($query) => $query->whereHas('registrations', fn ($r) => $r->where('event_id', $event->id)->where('status', 'approved')))
            ->with(['reservations' => fn ($r) => $r->when($event, fn ($x) => $x->where('event_id', $event->id))->whereNotNull('active_slot_id')->with('slot')])
            ->orderBy('brand_name')->limit(60)->get();

        return view('staff.producers', compact('producers', 'event', 'q'));
    }

    protected function visitorPayload(User $user, Event $event): array
    {
        $user->loadCount('checkins');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'member_code' => $user->member_code,
            'language' => $user->preferredLanguage()->value,
            'visits' => $user->checkins_count,
            'available_rewards' => $user->rewards()->where('status', RewardStatus::Available->value)->count(),
            'raffle_wins' => RaffleWinner::where('user_id', $user->id)->where('status', WinnerStatus::Won->value)->whereHas('draw', fn ($q) => $q->where('status', 'valid'))->count(),
            'url' => route('staff.visitor', ['user' => $user, 'event' => $event->id]),
        ];
    }
}
