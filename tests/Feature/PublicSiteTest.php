<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render_in_both_languages_with_hreflang(): void
    {
        $event = $this->openEvent();
        $producer = $this->approvedProducer();
        foreach (['ro', 'hu'] as $l) {
            foreach (['home', 'about', 'producers.index', 'events.index', 'blog.index', 'reviews.index', 'for-producers', 'shop', 'faq', 'contact', 'legal.terms', 'legal.privacy', 'legal.cookies', 'login', 'register'] as $r) {
                $this->get(route("{$l}.{$r}"))->assertOk()->assertSee('hreflang="hu"', false)->assertSee('hreflang="ro"', false);
            }
            $this->get(route("{$l}.events.show", $event->slug))->assertOk();
            $this->get(route("{$l}.producers.show", $producer->slug))->assertOk();
            $this->getJson(route("{$l}.events.map", $event->slug))->assertOk()->assertJsonStructure(['layout', 'slots']);
        }
        $this->get('/ro/producatori')->assertOk();
        $this->get('/hu/termelok')->assertOk();
        $this->get('/ro/targuri')->assertOk();
        $this->get('/hu/vasarok')->assertOk();
    }

    public function test_root_redirects_by_accept_language(): void
    {
        $this->get('/', ['Accept-Language' => 'hu-HU,hu;q=0.9'])->assertRedirect(route('hu.home'));
        $this->get('/', ['Accept-Language' => 'ro-RO'])->assertRedirect(route('ro.home'));
    }

    public function test_sitemap_contains_alternates(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee('hreflang="hu"', false);
    }

    public function test_shop_url_is_configurable_not_hardcoded(): void
    {
        $this->get(route('ro.home'))->assertDontSee('Autentik Shop ↗');
        Settings::set('integrations.shop_url', 'https://shop.example.test');
        $this->get(route('ro.home'))->assertSee('https://shop.example.test');
    }

    public function test_unapproved_producer_profile_is_not_public(): void
    {
        $p = Producer::factory()->create();
        $this->get(route('ro.producers.show', $p->slug))->assertNotFound();
    }

    public function test_custom_error_page(): void
    {
        $this->get('/ro/nu-exista-pagina')->assertNotFound()->assertSee('Autentik');
    }
}
