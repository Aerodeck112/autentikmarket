<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\ProducerCustomerInteraction;
use App\Models\Reward;
use App\Models\User;
use App\Models\Venue;
use App\Services\Events\EventLifecycleService;
use Database\Seeders\VenueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cumpărători: la ce ediție a târgului au fost și de la cine au cumpărat. */
class BuyerEditionsTest extends TestCase
{
    use RefreshDatabase;

    protected function edition(string $date, array $attrs = []): Event
    {
        $this->seed(VenueSeeder::class);
        $venue = Venue::where('code', 'TM')->firstOrFail();
        $event = app(EventLifecycleService::class)->create(array_merge(Event::factory()->raw(['event_date' => $date]), ['venue_id' => $venue->id], $attrs), $this->admin());
        $event->forceFill(['status' => EventStatus::Completed])->save();

        return $event->fresh();
    }

    public function test_editions_are_numbered_per_venue_and_can_be_set_by_hand(): void
    {
        $first = $this->edition('2026-08-01', ['edition' => 34]); // târgul a avut ediții înainte de platformă
        $next = $this->edition('2026-09-05');

        $this->assertSame(34, $first->edition);
        $this->assertSame(35, $next->edition);
        $this->assertSame('Ediția a 35-a', $next->editionLabel('ro'));
        $this->assertSame('35. kiadás', $next->editionLabel('hu'));
        $this->assertStringStartsWith('Ed. 35 · Târgu Mureș', $next->shortLabel());
    }

    public function test_organizer_records_a_visit_with_purchases(): void
    {
        $event = $this->edition('2026-09-05', ['edition' => 12]);
        $buyer = $this->visitor();
        [$a, $b] = [$this->approvedProducer(), $this->approvedProducer()];

        $this->actingAs($this->admin('organizer'))->post(route('crm.visitors.visits.store', $buyer), [
            'event_id' => $event->id, 'producer_ids' => [$a->id, $b->id],
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'Ed. 12') && str_contains($m, 'Cumpărături adăugate: 2'));

        $checkin = Checkin::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('crm', $checkin->method);
        $this->assertSame('2026-09-05', $checkin->checked_in_at->toDateString(), 'vizita are data ediției, nu data introducerii');
        $this->assertSame(2, ProducerCustomerInteraction::where('user_id', $buyer->id)->where('source', 'crm')->count());

        // a doua oară: nimic dublat
        $this->post(route('crm.visitors.visits.store', $buyer), ['event_id' => $event->id, 'producer_ids' => [$a->id]])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'era deja înregistrată'));
        $this->assertSame(1, Checkin::where('user_id', $buyer->id)->count());

        $this->get(route('crm.visitors.show', $buyer))->assertOk()->assertSee('Ediția a 12-a')->assertSee($a->brand_name)->assertSee($b->brand_name);
        $this->get(route('crm.visitors.index'))->assertSee('ed. 12')->assertSee($a->brand_name);
        $this->get(route('crm.visitors.index', ['event' => $event->id]))->assertSee($buyer->name);
    }

    public function test_crm_visits_count_for_loyalty_rewards(): void
    {
        $buyer = $this->visitor();
        $this->actingAs($this->admin());
        foreach (range(1, 5) as $i) {
            $e = $this->edition('2026-0'.$i.'-10');
            $this->post(route('crm.visitors.visits.store', $buyer), ['event_id' => $e->id]);
        }

        $this->assertSame(5, $buyer->checkins()->count());
        $this->assertSame(1, Reward::where('user_id', $buyer->id)->count(), 'pragul de 5 vizite dă cadoul');
    }

    public function test_new_buyer_can_be_added_with_his_visit(): void
    {
        $event = $this->edition('2026-09-05', ['edition' => 7]);
        $p = $this->approvedProducer();

        $this->actingAs($this->admin())->post(route('crm.visitors.store'), [
            'first_name' => 'Ilona', 'last_name' => 'Kiss', 'phone' => '0741111222', 'preferred_language' => 'hu', 'consent_terms' => '1',
            'event_id' => $event->id, 'producer_ids' => [$p->id],
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'Ed. 7'));

        $ilona = User::where('first_name', 'Ilona')->firstOrFail();
        $this->assertSame(1, $ilona->checkins()->count());
        $this->assertSame(1, $ilona->interactions()->count());
    }

    public function test_draft_or_cancelled_editions_cannot_be_used(): void
    {
        $event = $this->edition('2026-09-05');
        $event->forceFill(['status' => EventStatus::Draft])->save();

        $this->actingAs($this->admin())->post(route('crm.visitors.visits.store', $this->visitor()), ['event_id' => $event->id])
            ->assertSessionHasErrors('event_id');
    }

    public function test_export_lists_editions_and_producers(): void
    {
        $event = $this->edition('2026-09-05', ['edition' => 12]);
        $buyer = $this->visitor();
        $p = $this->approvedProducer();
        $this->actingAs($this->admin())->post(route('crm.visitors.visits.store', $buyer), ['event_id' => $event->id, 'producer_ids' => [$p->id]]);

        $csv = $this->get(route('crm.visitors.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('A cumpărat de la', $csv);
        $this->assertStringContainsString('TM ed. 12', $csv);
        $this->assertStringContainsString($p->brand_name, $csv);
    }
}
