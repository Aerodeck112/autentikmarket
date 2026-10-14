<?php

namespace App\Services\Checkin;

use App\Enums\EventStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Raffles\RaffleService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Check-in la târg. O vizită per utilizator per eveniment — garantat de UNIQUE(event_id, user_id).
 */
class CheckinService
{
    public function __construct(
        protected LoyaltyService $loyalty,
        protected RaffleService $raffles,
    ) {}

    public function findUserByToken(string $input): ?User
    {
        $input = trim($input);
        // Acceptă URL-ul complet din QR (…/staff/scan/{token}), token-ul brut sau codul de membru.
        if (preg_match('~/scan/([A-Za-z0-9]{20,64})~', $input, $m)) {
            $input = $m[1];
        }
        if (preg_match('/^AM-[A-Z0-9]{6}$/i', $input)) {
            return User::where('member_code', strtoupper($input))->first();
        }

        return strlen($input) >= 20 ? User::where('card_token', $input)->first() : null;
    }

    public function checkIn(Event $event, User $user, ?User $operator = null, string $method = 'qr'): CheckinResult
    {
        if (in_array($event->status, [EventStatus::Draft, EventStatus::Cancelled], true)) {
            throw new BusinessRuleException(__('Check-in-ul nu este disponibil pentru acest eveniment.'), 'event_not_active');
        }
        if (! $user->is_active || $user->anonymized_at) {
            throw new BusinessRuleException(__('Contul este inactiv.'), 'inactive_user');
        }

        $existing = Checkin::where('event_id', $event->id)->where('user_id', $user->id)->first();
        if ($existing) {
            return new CheckinResult($existing, false, $user->checkins()->count(), collect());
        }

        try {
            $checkin = DB::transaction(fn () => Checkin::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'operator_id' => $operator?->id,
                'method' => in_array($method, ['qr', 'manual'], true) ? $method : 'manual',
                'checked_in_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // Două scanări simultane: constraint-ul DB a blocat dublura.
            $existing = Checkin::where('event_id', $event->id)->where('user_id', $user->id)->firstOrFail();

            return new CheckinResult($existing, false, $user->checkins()->count(), collect());
        }

        if ($method === 'manual') {
            AuditLogger::log('checkin.manual', $checkin, [], ['user_id' => $user->id, 'event_id' => $event->id], $operator?->id);
        }

        $newRewards = $this->loyalty->evaluate($user, $event);
        $this->raffles->autoEnrollOnCheckin($event, $user);

        return new CheckinResult($checkin, true, $user->checkins()->count(), $newRewards);
    }
}
