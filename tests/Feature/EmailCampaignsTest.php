<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Mail\CampaignMail;
use App\Models\Checkin;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\Event;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\Campaigns\CampaignService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Campanii e-mail din CRM: public cu acord, limbă per destinatar, trimitere treptată, dezabonare. */
class EmailCampaignsTest extends TestCase
{
    use RefreshDatabase;

    protected function form(array $over = []): array
    {
        return array_merge([
            'name' => 'Târg de Crăciun', 'subject_ro' => 'Te așteptăm la târg, {{name}}!', 'subject_hu' => 'Várunk a vásáron, {{name}}!',
            'body_ro' => "Bună, {{name}}!\n\nDetalii: https://example.test/targ", 'body_hu' => "Szia, {{name}}!\n\nRészletek: https://example.test/vasar",
            'audience' => 'newsletter',
        ], $over);
    }

    public function test_only_buyers_with_consent_receive_campaigns_each_in_their_language(): void
    {
        Mail::fake();
        $ro = User::factory()->withRole('visitor')->create(['newsletter' => true, 'preferred_language' => 'ro', 'email' => 'ana@example.test']);
        $hu = User::factory()->withRole('visitor')->create(['marketing_consent' => true, 'preferred_language' => 'hu', 'email' => 'eva@example.test']);
        User::factory()->withRole('visitor')->create(['newsletter' => false, 'marketing_consent' => false, 'email' => 'fara@example.test']);
        NewsletterSubscriber::create(['email' => 'abonat@example.test', 'preferred_language' => 'hu', 'token' => str_repeat('a', 40), 'confirmed_at' => now()]);
        NewsletterSubscriber::create(['email' => 'neconfirmat@example.test', 'preferred_language' => 'ro', 'token' => str_repeat('b', 40)]);
        $admin = $this->admin('organizer');

        $this->actingAs($admin)->post(route('crm.campaigns.store'), $this->form(['audience' => 'buyers_consent']))->assertSessionHasNoErrors();
        $campaign = EmailCampaign::firstOrFail();
        $this->get(route('crm.campaigns.show', $campaign))->assertOk()->assertSee('3')->assertSee('Te așteptăm la târg');

        $this->post(route('crm.campaigns.start', $campaign))->assertSessionHas('success');
        $this->assertSame('sending', $campaign->fresh()->status);
        $this->assertEqualsCanonicalizing(['ana@example.test', 'eva@example.test', 'abonat@example.test'], $campaign->recipients()->pluck('email')->all());

        $sent = app(CampaignService::class)->tick();
        $this->assertSame(3, $sent);
        $campaign->refresh();
        $this->assertSame(['sent', 3], [$campaign->status, $campaign->sent_count]);
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo('eva@example.test') && str_contains($m->mailSubject, 'Várunk a vásáron') && str_contains($m->htmlBody, 'Dezabonare') === false && str_contains($m->htmlBody, 'Leiratkozás'));
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo('ana@example.test') && str_contains($m->mailSubject, 'Te așteptăm la târg, '.$ro->first_name) && str_contains($m->htmlBody, 'Dezabonare'));

        // o ciornă pornită nu se mai poate edita
        $this->get(route('crm.campaigns.edit', $campaign))->assertRedirect();
    }

    public function test_hourly_limit_spreads_sending_over_several_cron_runs(): void
    {
        Mail::fake();
        Settings::set('campaigns.hourly_limit', '60');
        User::factory()->count(5)->withRole('visitor')->create(['newsletter' => true]);
        $this->actingAs($this->admin())->post(route('crm.campaigns.store'), $this->form());
        $campaign = EmailCampaign::firstOrFail();
        $this->post(route('crm.campaigns.start', $campaign));

        $service = app(CampaignService::class);
        $this->assertSame(2, $service->tick(), '60/oră → câte 2 pe minut');
        $this->assertSame('sending', $campaign->fresh()->status);
        $this->post(route('crm.campaigns.pause', $campaign));
        $this->assertSame(0, $service->tick(), 'oprită: nu trimite');
        $this->post(route('crm.campaigns.pause', $campaign)); // reia
        $service->tick();
        $service->tick();
        $this->assertSame('sent', $campaign->fresh()->status);
        $this->assertSame(5, $campaign->fresh()->sent_count);
    }

    public function test_edition_audiences_and_producers(): void
    {
        $event = Event::factory()->create(['status' => EventStatus::Completed->value, 'event_date' => '2026-09-05']);
        $came = User::factory()->withRole('visitor')->create(['newsletter' => true]);
        $cameNoConsent = User::factory()->withRole('visitor')->create(['newsletter' => false, 'marketing_consent' => false]);
        User::factory()->withRole('visitor')->create(['newsletter' => true]); // nu a venit
        foreach ([$came, $cameNoConsent] as $u) {
            Checkin::create(['event_id' => $event->id, 'user_id' => $u->id, 'method' => 'crm', 'checked_in_at' => now()]);
        }
        $service = app(CampaignService::class);
        $this->assertSame([$came->email], $service->resolve('edition_buyers', $event->id)->keys()->all());

        $p1 = $this->approvedProducer('hu');
        $p2 = $this->approvedProducer();
        $p2->update(['campaigns_opt_out' => true]);
        $event->registrations()->create(['producer_id' => $p1->id, 'status' => 'approved']);
        $this->assertSame(['hu'], $service->resolve('edition_producers', $event->id)->pluck('language')->values()->all());
        $this->assertFalse($service->resolve('producers_approved')->has(strtolower($p2->email ?: $p2->user->email)), 'a renunțat la campanii');

        // publicul pe ediție cere ediția
        $this->actingAs($this->admin())->post(route('crm.campaigns.store'), $this->form(['audience' => 'edition_buyers']))->assertSessionHasErrors('event_id');
    }

    public function test_unsubscribe_link_withdraws_consent(): void
    {
        Mail::fake();
        $buyer = User::factory()->withRole('visitor')->create(['newsletter' => true, 'marketing_consent' => true, 'preferred_language' => 'hu']);
        $this->actingAs($this->admin())->post(route('crm.campaigns.store'), $this->form());
        $campaign = EmailCampaign::firstOrFail();
        $this->post(route('crm.campaigns.start', $campaign));
        auth()->logout();

        $r = EmailCampaignRecipient::firstOrFail();
        $url = app(CampaignService::class)->unsubscribeUrl($r);
        $this->get($url)->assertOk()->assertSee('Leiratkozom');
        $this->assertTrue($buyer->fresh()->newsletter, 'GET nu dezabonează (scanerele de linkuri)');
        $this->get(route('campaigns.unsubscribe', ['recipient' => $r->id]))->assertForbidden(); // fără semnătură

        // „dezabonare dintr-un clic” (POST fără CSRF)
        $this->post($url)->assertOk();
        $buyer->refresh();
        $this->assertFalse($buyer->newsletter);
        $this->assertFalse($buyer->marketing_consent);
        $this->assertDatabaseHas('consents', ['user_id' => $buyer->id, 'source' => 'campaign_unsubscribe', 'granted' => false]);
        $this->assertFalse(app(CampaignService::class)->resolve('newsletter')->has($buyer->email));
    }

    public function test_test_send_and_permissions(): void
    {
        Mail::fake();
        $admin = $this->admin('organizer');
        $this->actingAs($admin)->post(route('crm.campaigns.store'), $this->form());
        $campaign = EmailCampaign::firstOrFail();
        $this->post(route('crm.campaigns.test', $campaign))->assertSessionHas('success');
        Mail::assertSent(CampaignMail::class, 2);
        $this->assertSame(0, $campaign->recipients()->count(), 'testul nu pornește campania');

        $this->actingAs($this->admin('staff'))->get(route('crm.campaigns.index'))->assertForbidden();
    }
}
