<?php

namespace App\Services\Raffles;

use App\Enums\RaffleStatus;
use App\Enums\WinnerStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Raffle;
use App\Models\RaffleDraw;
use App\Models\RaffleEntry;
use App\Models\RaffleWinner;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Tombole corecte și auditabile:
 *  - snapshot al participanților eligibili + hash SHA-256;
 *  - randomizare criptografic sigură (random_int → Fisher–Yates);
 *  - rezultatele nu se modifică: o extragere poate fi doar ANULATĂ (cu motiv, în audit) și refăcută.
 */
class RaffleService
{
    public const ALGORITHM = 'fisher-yates/random_int (CSPRNG)';

    public function __construct(protected NotificationService $notifications) {}

    public function enter(Raffle $raffle, User $user, string $source = 'self'): RaffleEntry
    {
        $open = $source === 'self' ? $raffle->acceptsEntries() : $raffle->status === RaffleStatus::Open;
        if (! $open) {
            throw new BusinessRuleException(__('Înscrierile la această tombolă nu sunt deschise.'), 'raffle_closed');
        }

        try {
            return RaffleEntry::firstOrCreate(['raffle_id' => $raffle->id, 'user_id' => $user->id], ['source' => $source]);
        } catch (UniqueConstraintViolationException) {
            return RaffleEntry::where('raffle_id', $raffle->id)->where('user_id', $user->id)->firstOrFail();
        }
    }

    public function autoEnrollOnCheckin(Event $event, User $user): void
    {
        $event->raffles()
            ->where('status', RaffleStatus::Open->value)
            ->where('auto_enroll_on_checkin', true)
            ->get()
            ->each(fn (Raffle $r) => $this->enter($r, $user, 'checkin'));
    }

    public function isEligible(Raffle $raffle, User $user): bool
    {
        if (! $user->is_active || $user->anonymized_at) {
            return false;
        }
        if ($raffle->requires_checkin && ! Checkin::where('event_id', $raffle->event_id)->where('user_id', $user->id)->exists()) {
            return false;
        }
        if ($raffle->min_visits > 0 && $user->checkins()->count() < $raffle->min_visits) {
            return false;
        }

        return true;
    }

    /** @return list<int> ID-urile utilizatorilor eligibili (înscriși + condiții îndeplinite), sortate. */
    public function eligibleUserIds(Raffle $raffle): array
    {
        $q = RaffleEntry::query()
            ->where('raffle_entries.raffle_id', $raffle->id)
            ->join('users', 'users.id', '=', 'raffle_entries.user_id')
            ->where('users.is_active', true)
            ->whereNull('users.anonymized_at');

        if ($raffle->requires_checkin) {
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('checkins')
                ->whereColumn('checkins.user_id', 'raffle_entries.user_id')
                ->where('checkins.event_id', $raffle->event_id));
        }
        if ($raffle->min_visits > 0) {
            $q->whereRaw('(select count(*) from checkins c where c.user_id = raffle_entries.user_id) >= ?', [$raffle->min_visits]);
        }

        return $q->orderBy('raffle_entries.user_id')->pluck('raffle_entries.user_id')->map(fn ($id) => (int) $id)->all();
    }

    public function draw(Raffle $raffle, User $admin): RaffleDraw
    {
        $draw = DB::transaction(function () use ($raffle, $admin) {
            /** @var Raffle $locked */
            $locked = Raffle::whereKey($raffle->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [RaffleStatus::Open, RaffleStatus::Closed], true)) {
                throw new BusinessRuleException(__('Tombola nu poate fi extrasă în starea curentă.'), 'invalid_state');
            }
            if ($locked->draws()->where('status', 'valid')->exists()) {
                throw new BusinessRuleException(__('Tombola a fost deja extrasă.'), 'already_drawn');
            }

            // 1. Snapshot participanți eligibili
            $participants = $this->eligibleUserIds($locked);
            if ($participants === []) {
                throw new BusinessRuleException(__('Nu există participanți eligibili.'), 'no_participants');
            }

            // 3. Extragere: Fisher–Yates cu random_int (CSPRNG)
            $shuffled = $participants;
            for ($i = count($shuffled) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$shuffled[$i], $shuffled[$j]] = [$shuffled[$j], $shuffled[$i]];
            }
            $winnerIds = array_slice($shuffled, 0, min($locked->winners_count, count($shuffled)));

            // 2 + 5 + 6. Salvăm numărul participanților, data și administratorul
            $draw = RaffleDraw::create([
                'raffle_id' => $locked->id,
                'performed_by' => $admin->id,
                'performed_at' => now(),
                'participants_count' => count($participants),
                'participants_snapshot' => $participants,
                'snapshot_hash' => hash('sha256', json_encode($participants)),
                'algorithm' => self::ALGORITHM,
                'status' => 'valid',
            ]);

            // 4. Câștigătorii, cu premiile alocate în ordinea poziției
            $prizeSlots = [];
            foreach ($locked->prizes as $prize) {
                for ($q = 0; $q < max(1, $prize->quantity); $q++) {
                    $prizeSlots[] = $prize->id;
                }
            }
            foreach ($winnerIds as $pos => $userId) {
                RaffleWinner::create([
                    'raffle_draw_id' => $draw->id,
                    'raffle_id' => $locked->id,
                    'user_id' => $userId,
                    'raffle_prize_id' => $prizeSlots[$pos] ?? null,
                    'position' => $pos + 1,
                    'status' => WinnerStatus::Won,
                ]);
            }

            $locked->forceFill(['status' => RaffleStatus::Drawn])->save();

            AuditLogger::log('raffle.draw', $locked, [], [
                'draw_id' => $draw->id,
                'participants_count' => count($participants),
                'snapshot_hash' => $draw->snapshot_hash,
                'winners' => $winnerIds,
            ], $admin->id);

            return $draw;
        });

        foreach ($draw->winners()->with(['user', 'prize'])->get() as $winner) {
            $lang = $winner->user->preferredLanguage()->value;
            $this->notifications->send('RAFFLE_WINNER', $winner->user, [
                'raffle_name' => $raffle->t('name', $lang),
                'prize_name' => (string) $winner->prize?->t('name', $lang),
            ], ['related' => $winner, 'dedupe_key' => 'raffle-winner:'.$winner->id]);
        }

        return $draw;
    }

    /** Modificarea rezultatelor = anulare explicită, cu motiv, în audit log. */
    public function void(RaffleDraw $draw, User $admin, string $reason): void
    {
        DB::transaction(function () use ($draw, $admin, $reason) {
            $locked = RaffleDraw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'valid') {
                throw new BusinessRuleException(__('Extragerea este deja anulată.'), 'invalid_state');
            }
            if ($locked->winners()->where('status', WinnerStatus::Claimed->value)->exists()) {
                throw new BusinessRuleException(__('Un premiu a fost deja ridicat; extragerea nu mai poate fi anulată.'), 'prize_claimed');
            }
            $locked->update(['status' => 'voided', 'void_reason' => $reason, 'voided_by' => $admin->id, 'voided_at' => now()]);
            $locked->winners()->update(['status' => WinnerStatus::Forfeited->value]);
            $locked->raffle->forceFill(['status' => RaffleStatus::Closed])->save();

            AuditLogger::log('raffle.winner_modification', $locked->raffle, ['draw_id' => $locked->id, 'status' => 'valid'], ['status' => 'voided', 'reason' => $reason], $admin->id);
        });
    }

    public function claim(RaffleWinner $winner, User $operator): RaffleWinner
    {
        $affected = RaffleWinner::whereKey($winner->id)
            ->where('status', WinnerStatus::Won->value)
            ->whereHas('draw', fn ($q) => $q->where('status', 'valid'))
            ->update(['status' => WinnerStatus::Claimed->value, 'claimed_at' => now(), 'claimed_operator_id' => $operator->id]);

        if ($affected !== 1) {
            throw new BusinessRuleException(__('Premiul a fost deja ridicat sau extragerea nu mai este validă.'), 'already_claimed');
        }

        AuditLogger::log('raffle.prize_claimed', $winner, ['status' => WinnerStatus::Won], ['status' => WinnerStatus::Claimed], $operator->id);

        return $winner->fresh();
    }

    /**
     * Status vizibil clientului: not_eligible | eligible | entered | not_winner | winner | claimed.
     */
    public function userStatus(Raffle $raffle, User $user): string
    {
        $draw = $raffle->currentDraw;
        if ($draw) {
            $win = $draw->winners()->where('user_id', $user->id)->first();
            if ($win) {
                return $win->status === WinnerStatus::Claimed ? 'claimed' : 'winner';
            }
            if (in_array($user->id, $draw->participants_snapshot ?? [], true)) {
                return 'not_winner';
            }
        }
        if ($raffle->entries()->where('user_id', $user->id)->exists()) {
            return $this->isEligible($raffle, $user) ? 'entered' : 'not_eligible';
        }

        return $this->isEligible($raffle, $user) ? 'eligible' : 'not_eligible';
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'eligible' => __('Eligibil'),
            'entered' => __('Înscris'),
            'not_winner' => __('Necâștigător'),
            'winner' => __('Câștigător'),
            'claimed' => __('Premiu ridicat'),
            default => __('Neeligibil'),
        };
    }
}
