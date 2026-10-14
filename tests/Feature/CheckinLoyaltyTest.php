<?php

namespace Tests\Feature;

use App\Enums\RewardStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Account\AccountController;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Services\Checkin\CheckinService;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckinLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    protected function makeEvents(int $n): array
    {
        $events = [];
        for ($i = 0; $i < $n; $i++) {
            $e = Event::factory()->create(['event_date' => now()->subWeeks($n - $i)->toDateString()]);
            $e->forceFill(['status' => 'active'])->save();
            $events[] = $e;
        }

        return $events;
    }

    public function test_visitor_cannot_get_two_visits_for_the_same_event(): void
    {
        [$event] = $this->makeEvents(1);
        $user = $this->visitor();
        $service = app(CheckinService::class);

        $first = $service->checkIn($event, $user, $this->admin('staff'));
        $second = $service->checkIn($event, $user, $this->admin('staff'));

        $this->assertTrue($first->created);
        $this->assertFalse($second->created);
        $this->assertSame(1, Checkin::where('event_id', $event->id)->where('user_id', $user->id)->count());
    }

    public function test_database_constraint_prevents_duplicate_checkin(): void
    {
        [$event] = $this->makeEvents(1);
        $user = $this->visitor();
        Checkin::create(['event_id' => $event->id, 'user_id' => $user->id, 'checked_in_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);
        Checkin::create(['event_id' => $event->id, 'user_id' => $user->id, 'checked_in_at' => now()]);
    }

    public function test_reward_is_generated_exactly_at_threshold(): void
    {
        Mail::fake();
        $events = $this->makeEvents(5);
        $user = $this->visitor('hu');
        $service = app(CheckinService::class);

        foreach (array_slice($events, 0, 4) as $e) {
            $service->checkIn($e, $user);
        }
        $this->assertSame(0, Reward::where('user_id', $user->id)->count(), 'Nicio recompensă înainte de prag.');

        $result = $service->checkIn($events[4], $user);
        $this->assertCount(1, $result->newRewards);
        $reward = Reward::where('user_id', $user->id)->sole();
        $this->assertSame(5, $reward->rule->threshold);
        // notificarea în limba clientului (HU)
        $this->assertDatabaseHas('notification_logs', ['template_key' => 'LOYALTY_REWARD_EARNED', 'language' => 'hu', 'notifiable_id' => $user->id]);
    }

    public function test_reward_cannot_be_redeemed_twice(): void
    {
        $events = $this->makeEvents(5);
        $user = $this->visitor();
        foreach ($events as $e) {
            app(CheckinService::class)->checkIn($e, $user);
        }
        $reward = Reward::where('user_id', $user->id)->sole();
        $staff = $this->admin('staff');
        $loyalty = app(LoyaltyService::class);

        $loyalty->redeem($reward, $staff, $events[4]);
        $this->assertSame(RewardStatus::Redeemed, $reward->fresh()->status);

        $this->expectException(BusinessRuleException::class);
        $loyalty->redeem($reward->fresh(), $staff, $events[4]);
    }

    public function test_redemption_unique_constraint(): void
    {
        $events = $this->makeEvents(5);
        $user = $this->visitor();
        foreach ($events as $e) {
            app(CheckinService::class)->checkIn($e, $user);
        }
        $reward = Reward::where('user_id', $user->id)->sole();
        RewardRedemption::create(['reward_id' => $reward->id, 'user_id' => $user->id, 'redeemed_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);
        RewardRedemption::create(['reward_id' => $reward->id, 'user_id' => $user->id, 'redeemed_at' => now()]);
    }

    public function test_staff_checkin_endpoint_and_qr_token_lookup(): void
    {
        $event = Event::factory()->create(['event_date' => now()->toDateString()]);
        $event->forceFill(['status' => 'active'])->save();
        $user = $this->visitor();
        $staff = $this->admin('staff');

        $this->actingAs($staff)->postJson(route('staff.checkin'), ['event_id' => $event->id, 'code' => route('staff.scan', $user->card_token)])
            ->assertOk()->assertJson(['created' => true]);
        $this->actingAs($staff)->postJson(route('staff.checkin'), ['event_id' => $event->id, 'code' => $user->member_code])
            ->assertOk()->assertJson(['created' => false]);
        $this->actingAs($staff)->postJson(route('staff.checkin'), ['event_id' => $event->id, 'code' => 'nonsense'])->assertNotFound();
    }

    public function test_qr_contains_no_personal_data(): void
    {
        $user = $this->visitor();
        $payload = AccountController::cardPayload($user);

        $this->assertStringNotContainsString($user->email, $payload);
        $this->assertStringNotContainsString($user->last_name, $payload);
        $this->assertStringContainsString($user->card_token, $payload);
    }
}
