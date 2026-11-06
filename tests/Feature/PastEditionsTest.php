<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Jobs\RunPostEventWorkflow;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\NotificationLog;
use App\Models\Task;
use App\Models\Venue;
use App\Services\Events\EventLifecycleService;
use Database\Seeders\VenueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Ediții din trecut adăugate retroactiv: arhivă, fără e-mailuri și task-uri, cu producătorii prezenți. */
class PastEditionsTest extends TestCase
{
    use RefreshDatabase;

    protected function form(array $over = []): array
    {
        $this->seed(VenueSeeder::class);

        return array_merge([
            'title_ro' => 'Autentik Market Târgu Mureș', 'title_hu' => 'Autentik Market Marosvásárhely',
            'venue_id' => Venue::where('code', 'TM')->value('id'), 'city' => 'Târgu Mureș',
            'event_date' => '2024-05-12', 'start_time' => '08:00', 'end_time' => '15:00', 'edition' => 7,
        ], $over);
    }

    public function test_past_edition_is_archived_without_emails_or_tasks(): void
    {
        Queue::fake();
        $this->approvedProducer(); // id-urile producătorilor diferă de cele ale înscrierilor
        [$a, $b] = [$this->approvedProducer(), $this->approvedProducer()];
        $admin = $this->admin('organizer');

        $this->actingAs($admin)->get(route('crm.events.create', ['past' => 1]))->assertOk()->assertSee('Ediție trecută (adăugată retroactiv)')->assertSee($a->brand_name);
        $this->post(route('crm.events.store'), $this->form(['edition' => '']))->assertSessionHasErrors('edition');
        $this->post(route('crm.events.store'), $this->form(['present_form' => '1', 'present_ids' => [$a->id, $b->id]]))
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'arhivă'));

        $event = Event::where('edition', 7)->firstOrFail();
        $this->assertSame(EventStatus::Completed, $event->status);
        $this->assertNotNull($event->archived_at);
        $this->assertNotNull($event->post_event_processed_at, 'fluxul de după târg nu mai rulează niciodată');
        $this->assertSame(0, Task::where('event_id', $event->id)->count());
        Queue::assertNotPushed(RunPostEventWorkflow::class);

        $regs = EventRegistration::where('event_id', $event->id)->get();
        $this->assertCount(2, $regs);
        $this->assertTrue($regs->every(fn ($r) => $r->status === RegistrationStatus::Approved && $r->attended === true));

        // workflow-ul de după târg este refuzat pentru o ediție din arhivă
        $this->post(route('crm.events.post-event', $event))->assertSessionHas('error');
        $this->artisan('events:post-event')->assertSuccessful();
        $this->assertSame(0, NotificationLog::count());

        // pagina ediției + ediția apare la înregistrarea vizitei unui cumpărător, cu producătorii marcați
        $this->get(route('crm.events.show', $event))->assertOk()->assertSee('Ediție din arhivă');
        $this->get(route('crm.visitors.show', $this->visitor()))->assertOk()->assertSee('Ed. 7 · Târgu Mureș · 12.05.2024');

        // editare: debifarea unui producător îl scoate (înscriere fără rezervare)
        $this->put(route('crm.events.update', $event), $this->form(['present_form' => '1', 'present_ids' => [$a->id]]))->assertSessionHasNoErrors();
        $this->assertSame([$a->id], EventRegistration::where('event_id', $event->id)->pluck('producer_id')->all());
    }

    public function test_future_event_ignores_the_present_producers_list(): void
    {
        $p = $this->approvedProducer();
        $this->actingAs($this->admin())->post(route('crm.events.store'), $this->form([
            'event_date' => now()->addMonth()->toDateString(), 'present_form' => '1', 'present_ids' => [$p->id],
        ]))->assertSessionHasNoErrors();

        $event = Event::where('edition', 7)->firstOrFail();
        $this->assertNotSame(EventStatus::Completed, $event->status);
        $this->assertNull($event->archived_at);
        $this->assertSame(0, EventRegistration::where('event_id', $event->id)->count());
        $this->assertGreaterThan(0, Task::where('event_id', $event->id)->count());
    }

    public function test_old_draft_event_can_be_moved_to_the_archive(): void
    {
        $this->seed(VenueSeeder::class);
        $event = Event::factory()->create(['event_date' => '2023-06-01']); // ciornă, intrată înainte de această versiune
        $this->assertTrue(EventLifecycleService::canArchive($event));

        $this->actingAs($this->admin())->get(route('crm.events.show', $event))->assertSee('Trece în arhivă');
        $this->post(route('crm.events.archive', $event))->assertSessionHas('success');

        $event->refresh();
        $this->assertSame(EventStatus::Completed, $event->status);
        $this->assertNotNull($event->archived_at);
        $this->assertSame(0, NotificationLog::count());

        // un eveniment viitor nu se poate arhiva
        $future = Event::factory()->create(['event_date' => now()->addWeek()->toDateString()]);
        $this->post(route('crm.events.archive', $future))->assertSessionHas('error');
        $this->assertSame(EventStatus::Draft, $future->fresh()->status);
    }
}
