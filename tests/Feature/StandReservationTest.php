<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\SlotStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Producer;
use App\Models\StandReservation;
use App\Services\Reservations\StandReservationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_producers_cannot_reserve_the_same_stand(): void
    {
        $event = $this->openEvent();
        $slot = $event->slots()->where('status', 'available')->first();
        $a = $this->approvedProducer();
        $b = $this->approvedProducer();
        $service = app(StandReservationService::class);

        $service->hold($event, $slot, $a);

        try {
            $service->hold($event, $slot, $b);
            $this->fail('Al doilea producător nu trebuia să primească standul.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('slot_taken', $e->reason);
        }

        $this->assertSame(1, StandReservation::whereNotNull('active_slot_id')->where('event_slot_id', $slot->id)->count());
        $this->assertSame(SlotStatus::Hold, $slot->fresh()->status);
    }

    public function test_database_constraint_blocks_two_active_reservations_for_one_stand(): void
    {
        // Chiar dacă logica aplicației ar fi ocolită, UNIQUE(active_slot_id) blochează dublura.
        $event = $this->openEvent();
        $slot = $event->slots()->first();
        $base = ['event_id' => $event->id, 'event_slot_id' => $slot->id, 'status' => ReservationStatus::Requested, 'active_slot_id' => $slot->id];

        StandReservation::create($base + ['producer_id' => $this->approvedProducer()->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        StandReservation::create($base + ['producer_id' => $this->approvedProducer()->id]);
    }

    public function test_expired_hold_releases_the_stand_for_another_producer(): void
    {
        $event = $this->openEvent();
        $slot = $event->slots()->where('status', 'available')->first();
        $service = app(StandReservationService::class);

        $first = $service->hold($event, $slot, $this->approvedProducer());
        $this->travel(16)->minutes();

        $second = $service->hold($event, $slot, $this->approvedProducer());

        $this->assertSame(ReservationStatus::Expired, $first->fresh()->status);
        $this->assertNull($first->fresh()->active_slot_id);
        $this->assertTrue($second->isActive());
    }

    public function test_scheduler_command_expires_holds(): void
    {
        $event = $this->openEvent();
        $slot = $event->slots()->where('status', 'available')->first();
        app(StandReservationService::class)->hold($event, $slot, $this->approvedProducer());
        $this->travel(20)->minutes();

        $this->artisan('reservations:expire-holds')->assertSuccessful();

        $this->assertSame(SlotStatus::Available, $slot->fresh()->status);
    }

    public function test_full_flow_request_and_approval_marks_stand_reserved(): void
    {
        $event = $this->openEvent();
        $slot = $event->slots()->where('status', 'available')->first();
        $producer = $this->approvedProducer();
        $service = app(StandReservationService::class);

        $r = $service->hold($event, $slot, $producer);
        $service->request($r, $producer, ['needs_table' => true]);
        $this->assertSame(SlotStatus::Requested, $slot->fresh()->status);

        $service->approve($r->fresh(), $this->admin());
        $this->assertSame(ReservationStatus::Approved, $r->fresh()->status);
        $this->assertSame(SlotStatus::Reserved, $slot->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'stand_reservation.approved']);
        $this->assertDatabaseHas('annexes', ['stand_reservation_id' => $r->id]);
    }

    public function test_unapproved_producer_cannot_hold(): void
    {
        $event = $this->openEvent();
        $producer = Producer::factory()->create();

        $this->expectException(BusinessRuleException::class);
        app(StandReservationService::class)->hold($event, $event->slots()->first(), $producer);
    }

    public function test_hold_endpoint_returns_conflict_for_taken_stand(): void
    {
        $event = $this->openEvent();
        $slot = $event->slots()->where('status', 'available')->first();
        $a = $this->approvedProducer();
        $b = $this->approvedProducer();
        foreach ([$a, $b] as $p) {
            $this->actingAs($p->user)->post(route('producer.events.confirm', $event))->assertRedirect();
        }

        $this->actingAs($a->user)->postJson(route('producer.events.hold', [$event, $slot]))->assertOk();
        $this->actingAs($b->user)->postJson(route('producer.events.hold', [$event, $slot]))->assertStatus(409)->assertJson(['reason' => 'slot_taken']);
    }
}
