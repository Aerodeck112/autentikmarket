<?php

namespace App\Services\Stats;

use App\Enums\EventStatus;
use App\Enums\ProducerStatus;
use App\Enums\RegistrationStatus;
use App\Enums\ReviewStatus;
use App\Enums\SlotStatus;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Producer;
use App\Models\ProducerCategory;
use App\Models\RewardRedemption;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Statistici per eveniment și globale, inclusiv RAPORTARE PE LIMBI (RO/HU).
 */
class StatisticsService
{
    public function eventStats(Event $event): array
    {
        $regs = $event->registrations();
        $registered = (clone $regs)->whereIn('status', [RegistrationStatus::Submitted->value, RegistrationStatus::Approved->value, RegistrationStatus::Rejected->value])->count();
        $accepted = (clone $regs)->where('status', RegistrationStatus::Approved->value)->count();
        $absent = (clone $regs)->where('status', RegistrationStatus::Approved->value)->where('attended', false)->count();
        $present = $accepted - $absent;

        $slotCounts = $event->slots()->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status')->all();
        $slotsTotal = array_sum($slotCounts);
        $occupied = ($slotCounts[SlotStatus::Reserved->value] ?? 0) + ($slotCounts[SlotStatus::Occupied->value] ?? 0);
        $sellable = $slotsTotal - ($slotCounts[SlotStatus::Blocked->value] ?? 0) - ($slotCounts[SlotStatus::Unavailable->value] ?? 0);

        // Vizitatori: total, noi (primul check-in = acest eveniment), recurenți, RO/HU
        $visitorRows = Checkin::query()
            ->where('checkins.event_id', $event->id)
            ->join('users', 'users.id', '=', 'checkins.user_id')
            ->select('users.preferred_language', 'checkins.user_id')
            ->selectSub(fn ($q) => $q->from('checkins as c2')->selectRaw('count(*)')
                ->whereColumn('c2.user_id', 'checkins.user_id')
                ->where('c2.checked_in_at', '<', DB::raw('checkins.checked_in_at')), 'previous')
            ->get();
        $visitorsTotal = $visitorRows->count();
        $visitorsNew = $visitorRows->where('previous', 0)->count();
        $visitorLang = $this->langSplit($visitorRows->pluck('preferred_language'));

        $producerLangs = Producer::query()
            ->whereIn('id', (clone $regs)->where('status', RegistrationStatus::Approved->value)->select('producer_id'))
            ->pluck('preferred_language');
        $producerLang = $this->langSplit($producerLangs);

        $reviews = $event->reviews()->where('status', ReviewStatus::Approved->value);
        $raffleParticipants = DB::table('raffle_entries')->whereIn('raffle_id', $event->raffles()->select('id'))->count();

        $categories = ProducerCategory::query()
            ->whereIn('id', Producer::whereIn('id', (clone $regs)->where('status', RegistrationStatus::Approved->value)->select('producer_id'))->select('producer_category_id'))
            ->orderBy('sort_order')->get();

        return [
            'producers_registered' => $registered,
            'producers_accepted' => $accepted,
            'producers_present' => $present,
            'producers_absent' => $absent,
            'slots_total' => $slotsTotal,
            'slots_available' => $slotCounts[SlotStatus::Available->value] ?? 0,
            'slots_occupied' => $occupied,
            'slots_pending' => ($slotCounts[SlotStatus::Hold->value] ?? 0) + ($slotCounts[SlotStatus::Requested->value] ?? 0),
            'occupancy_pct' => $sellable > 0 ? round($occupied / $sellable * 100, 1) : 0.0,
            'visitors_total' => $visitorsTotal,
            'visitors_new' => $visitorsNew,
            'visitors_returning' => $visitorsTotal - $visitorsNew,
            'visitors_ro' => $visitorLang['ro'],
            'visitors_hu' => $visitorLang['hu'],
            'visitors_ro_pct' => $this->pct($visitorLang['ro'], $visitorsTotal),
            'visitors_hu_pct' => $this->pct($visitorLang['hu'], $visitorsTotal),
            'producers_ro' => $producerLang['ro'],
            'producers_hu' => $producerLang['hu'],
            'producers_ro_pct' => $this->pct($producerLang['ro'], $producerLangs->count()),
            'producers_hu_pct' => $this->pct($producerLang['hu'], $producerLangs->count()),
            'interactions' => $event->interactions()->count(),
            'reviews' => (clone $reviews)->count(),
            'rating' => round((float) (clone $reviews)->avg('rating'), 2),
            'raffle_participants' => $raffleParticipants,
            'rewards_redeemed' => RewardRedemption::where('event_id', $event->id)->count(),
            'categories_ro' => $categories->pluck('name_ro')->all(),
            'categories_hu' => $categories->pluck('name_hu')->all(),
        ];
    }

    public function dashboard(): array
    {
        $next = Event::upcoming()->with('venue')->first();
        $nextStats = $next ? $this->eventStats($next) : null;

        $visitors = User::query()->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['producer', 'staff', 'organizer', 'admin', 'super_admin']))->whereNull('anonymized_at');
        $visitorsTotal = (clone $visitors)->count();
        $visitorLang = (clone $visitors)->select('preferred_language', DB::raw('count(*) as c'))->groupBy('preferred_language')->pluck('c', 'preferred_language');
        $returning = (clone $visitors)->has('checkins', '>=', 2)->count();

        $producerLang = Producer::approved()->select('preferred_language', DB::raw('count(*) as c'))->groupBy('preferred_language')->pluck('c', 'preferred_language');
        $producersApproved = (int) $producerLang->sum();

        $today = now()->toDateString();

        return [
            'next_event' => $next,
            'next_stats' => $nextStats,
            'tasks_overdue' => Task::overdue()->count(),
            'tasks_today' => Task::open()->whereDate('due_date', $today)->count(),
            'tasks_week' => Task::open()->whereDate('due_date', '>', $today)->whereDate('due_date', '<=', now()->addDays(7)->toDateString())->count(),
            'producers_new' => Producer::whereIn('status', [ProducerStatus::New->value, ProducerStatus::Incomplete->value])->count(),
            'producers_review' => Producer::whereIn('status', [ProducerStatus::DocumentsSubmitted->value, ProducerStatus::InReview->value])->count(),
            'producers_approved' => $producersApproved,
            'visitors_total' => $visitorsTotal,
            'visitors_ro' => (int) ($visitorLang['ro'] ?? 0),
            'visitors_hu' => (int) ($visitorLang['hu'] ?? 0),
            'visitors_ro_pct' => $this->pct((int) ($visitorLang['ro'] ?? 0), $visitorsTotal),
            'visitors_hu_pct' => $this->pct((int) ($visitorLang['hu'] ?? 0), $visitorsTotal),
            'visitors_new_30d' => (clone $visitors)->where('created_at', '>=', now()->subDays(30))->count(),
            'visitors_returning' => $returning,
            'producers_ro' => (int) ($producerLang['ro'] ?? 0),
            'producers_hu' => (int) ($producerLang['hu'] ?? 0),
            'producers_ro_pct' => $this->pct((int) ($producerLang['ro'] ?? 0), $producersApproved),
            'producers_hu_pct' => $this->pct((int) ($producerLang['hu'] ?? 0), $producersApproved),
            'events_completed' => Event::where('status', EventStatus::Completed->value)->count(),
        ];
    }

    /** Raport pe limbi pentru toate evenimentele încheiate (sau filtrate). */
    public function languageReport(?string $city = null): array
    {
        return Event::query()
            ->whereIn('status', [EventStatus::Completed->value, EventStatus::Active->value])
            ->when($city, fn ($q) => $q->where('city', $city))
            ->orderByDesc('event_date')->limit(50)->get()
            ->map(fn (Event $e) => ['event' => $e] + $this->eventStats($e))
            ->all();
    }

    protected function langSplit($values): array
    {
        $out = ['ro' => 0, 'hu' => 0];
        foreach ($values as $v) {
            $v = $v instanceof \BackedEnum ? $v->value : (string) $v;
            $out[$v === 'hu' ? 'hu' : 'ro']++;
        }

        return $out;
    }

    protected function pct(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }
}
