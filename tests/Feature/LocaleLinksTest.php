<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Comutatorul RO/HU și hreflang pe paginile cu slug (producător, târg) nu duc la 404. */
class LocaleLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_switch_on_producer_and_event_pages_keeps_the_slug(): void
    {
        $producer = $this->approvedProducer();
        $producer->update(['is_public' => true]);
        $event = Event::factory()->create(['status' => 'registrations_open']);

        foreach ([lroute('producers.show', $producer, 'ro'), lroute('events.show', $event, 'ro')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match('/<link rel="alternate" hreflang="hu" href="([^"]+)"/', $html, $m);
            $this->assertNotEmpty($m, $url);
            $this->assertStringNotContainsString('/'.$producer->id.'"', $m[0]);
            $this->get($m[1])->assertOk();
        }
    }

    public function test_hungarian_url_with_romanian_words_redirects(): void
    {
        $producer = $this->approvedProducer();
        $producer->update(['is_public' => true]);

        $this->get('/hu/producatori/'.$producer->slug)->assertRedirect(lroute('producers.show', $producer, 'hu'));
        $this->get('/ro/termelok')->assertRedirect(lroute('producers.index', [], 'ro'));
        $this->get('/hu/nu-exista')->assertNotFound();
        $this->get('/nimic-aici')->assertNotFound();
    }
}
