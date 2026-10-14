<?php

namespace App\Services\Loyalty;

use App\Enums\RewardStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Event;
use App\Models\LoyaltyRule;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fidelizare: regulile (praguri) sunt configurabile din CRM — nimic hardcodat.
 */
class LoyaltyService
{
    public function __construct(protected NotificationService $notifications) {}

    /**
     * Generează recompensele pentru toate pragurile atinse și încă neacordate.
     *
     * @return Collection<int, Reward> recompensele NOI
     */
    public function evaluate(User $user, ?Event $event = null): Collection
    {
        $visits = $user->checkins()->count();
        $rules = LoyaltyRule::where('active', true)->where('threshold', '<=', $visits)->orderBy('threshold')->get();
        $existing = $user->rewards()->pluck('loyalty_rule_id')->all();

        $created = collect();
        foreach ($rules as $rule) {
            if (in_array($rule->id, $existing, true)) {
                continue;
            }
            try {
                $reward = Reward::create([
                    'user_id' => $user->id,
                    'loyalty_rule_id' => $rule->id,
                    'earned_event_id' => $event?->id,
                    'status' => RewardStatus::Available,
                    'earned_at' => now(),
                    'expires_at' => $rule->valid_days ? now()->addDays($rule->valid_days) : null,
                ]);
                $created->push($reward);
            } catch (UniqueConstraintViolationException) {
                // Deja acordată în paralel — UNIQUE(user_id, loyalty_rule_id).
            }
        }

        foreach ($created as $reward) {
            $lang = $user->preferredLanguage()->value;
            $this->notifications->send('LOYALTY_REWARD_EARNED', $user, [
                'reward_title' => $reward->rule->t('title', $lang),
                'reward_description' => (string) $reward->rule->t('description', $lang),
                'visits' => $visits,
            ], [
                'related' => $reward,
                'dedupe_key' => 'reward-earned:'.$reward->id,
                'action_url' => route('account.card'),
                'action_label_ro' => 'Vezi cardul Autentik',
                'action_label_hu' => 'Autentik kártya megtekintése',
            ]);
        }

        return $created;
    }

    /** Următorul prag (pentru progres pe card). */
    public function nextRule(User $user): ?LoyaltyRule
    {
        $visits = $user->checkins()->count();

        return LoyaltyRule::where('active', true)->where('threshold', '>', $visits)->orderBy('threshold')->first();
    }

    /**
     * „MARCAȚI CADOU RIDICAT”. Atomic: UPDATE … WHERE status = available + UNIQUE(reward_id).
     */
    public function redeem(Reward $reward, User $operator, ?Event $event = null): RewardRedemption
    {
        try {
            return DB::transaction(function () use ($reward, $operator, $event) {
                $affected = Reward::whereKey($reward->id)
                    ->where('status', RewardStatus::Available->value)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->update(['status' => RewardStatus::Redeemed->value, 'updated_at' => now()]);

                if ($affected !== 1) {
                    throw new BusinessRuleException(__('Recompensa a fost deja ridicată sau nu mai este valabilă.'), 'already_redeemed');
                }

                $redemption = RewardRedemption::create([
                    'reward_id' => $reward->id,
                    'user_id' => $reward->user_id,
                    'event_id' => $event?->id,
                    'operator_id' => $operator->id,
                    'redeemed_at' => now(),
                ]);

                AuditLogger::log('reward.redeemed', $reward, ['status' => RewardStatus::Available], [
                    'status' => RewardStatus::Redeemed, 'event_id' => $event?->id, 'operator_id' => $operator->id,
                ], $operator->id);

                return $redemption;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException(__('Recompensa a fost deja ridicată sau nu mai este valabilă.'), 'already_redeemed');
        }
    }

    public function expireRewards(): int
    {
        return Reward::where('status', RewardStatus::Available->value)
            ->whereNotNull('expires_at')->where('expires_at', '<', now())
            ->update(['status' => RewardStatus::Expired->value]);
    }
}
