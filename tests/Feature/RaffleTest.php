<?php

namespace Tests\Feature;

use App\Enums\RaffleStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Event;
use App\Models\Raffle;
use App\Services\Checkin\CheckinService;
use App\Services\Raffles\RaffleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaffleTest extends TestCase
{
    use RefreshDatabase;

    protected function raffle(Event $event, int $winners = 2): Raffle
    {
        $r = Raffle::create(['event_id' => $event->id, 'name_ro' => 'Tombola', 'name_hu' => 'Tombola', 'winners_count' => $winners, 'requires_checkin' => true, 'auto_enroll_on_checkin' => true]);
        $r->forceFill(['status' => RaffleStatus::Open])->save();
        $r->prizes()->create(['name_ro' => 'Coș', 'name_hu' => 'Kosár', 'quantity' => $winners]);

        return $r;
    }

    public function test_only_eligible_participants_are_drawn(): void
    {
        $event = Event::factory()->create();
        $event->forceFill(['status' => 'active'])->save();
        $raffle = $this->raffle($event, 10);
        $service = app(RaffleService::class);

        $eligible = collect(range(1, 3))->map(function () use ($event) {
            $u = $this->visitor();
            app(CheckinService::class)->checkIn($event, $u); // înscriere automată la check-in

            return $u;
        });
        // Înscris fără check-in → NEeligibil
        $noCheckin = $this->visitor();
        $service->enter($raffle, $noCheckin, 'staff');
        // Cont dezactivat → NEeligibil
        $inactive = $eligible->first();
        $inactive->forceFill(['is_active' => false])->save();

        $draw = $service->draw($raffle, $this->admin());

        $winnerIds = $draw->winners->pluck('user_id')->sort()->values()->all();
        $expected = $eligible->slice(1)->pluck('id')->sort()->values()->all();
        $this->assertSame($expected, $winnerIds);
        $this->assertSame(2, $draw->participants_count);
        $this->assertSame(hash('sha256', json_encode($draw->participants_snapshot)), $draw->snapshot_hash);
        $this->assertNotContains($noCheckin->id, $draw->participants_snapshot);
        $this->assertSame(RaffleStatus::Drawn, $raffle->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'raffle.draw']);
    }

    public function test_raffle_cannot_be_drawn_twice_and_void_is_audited(): void
    {
        $event = Event::factory()->create();
        $event->forceFill(['status' => 'active'])->save();
        $raffle = $this->raffle($event, 1);
        app(CheckinService::class)->checkIn($event, $this->visitor());
        $service = app(RaffleService::class);
        $admin = $this->admin();

        $draw = $service->draw($raffle, $admin);
        try {
            $service->draw($raffle->fresh(), $admin);
            $this->fail('A doua extragere trebuia blocată.');
        } catch (BusinessRuleException) {
        }

        $service->void($draw, $admin, 'Eroare de procedură constatată de comisie.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'raffle.winner_modification']);
        $this->assertSame('voided', $draw->fresh()->status);
        $this->assertSame(RaffleStatus::Closed, $raffle->fresh()->status);
    }

    public function test_user_status_labels(): void
    {
        $event = Event::factory()->create();
        $event->forceFill(['status' => 'active'])->save();
        $raffle = $this->raffle($event, 1);
        $service = app(RaffleService::class);
        $outsider = $this->visitor();
        $this->assertSame('not_eligible', $service->userStatus($raffle, $outsider));

        $winner = $this->visitor();
        app(CheckinService::class)->checkIn($event, $winner);
        $this->assertSame('entered', $service->userStatus($raffle, $winner));

        $service->draw($raffle, $this->admin());
        $this->assertSame('winner', $service->userStatus($raffle->fresh(), $winner));
    }
}
