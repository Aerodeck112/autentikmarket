<?php

namespace Tests\Feature;

use App\Enums\ProducerStatus;
use App\Models\Consent;
use App\Models\NotificationLog;
use App\Models\Producer;
use App\Models\User;
use App\Services\Accounts\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Producători și cumpărători adăugați de organizator din CRM + invitația de alegere a parolei. */
class CrmAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function producerForm(array $over = []): array
    {
        return array_merge([
            'brand_name' => 'Livada Test', 'preferred_language' => 'hu', 'city' => 'Reghin', 'phone' => '0740000000',
            'email' => 'livada@example.test', 'is_public' => '1',
        ], $over);
    }

    public function test_producer_without_account(): void
    {
        $this->actingAs($this->admin())->post(route('crm.producers.store'), $this->producerForm(['create_account' => '0']))->assertRedirect();

        $p = Producer::where('brand_name', 'Livada Test')->firstOrFail();
        $this->assertNull($p->user_id);
        $this->assertSame(ProducerStatus::New, $p->status);
        $this->assertSame('livada@example.test', $p->email);
    }

    public function test_producer_with_account_is_approved_and_invited_in_his_language(): void
    {
        $this->actingAs($this->admin())->post(route('crm.producers.store'), $this->producerForm([
            'create_account' => '1', 'contact_first_name' => 'Bartha', 'contact_last_name' => 'Anna', 'approve' => '1',
        ]))->assertRedirect()->assertSessionHas('success');

        $p = Producer::where('brand_name', 'Livada Test')->firstOrFail();
        $this->assertSame(ProducerStatus::Approved, $p->status);
        $this->assertTrue($p->user->hasRole('producer'));
        $log = NotificationLog::where('template_key', 'PRODUCER_INVITE')->firstOrFail();
        $this->assertSame('hu', $log->language);
        $this->assertStringContainsString('/invite/'.$p->user->id, $log->body);
    }

    public function test_account_is_required_to_have_an_email(): void
    {
        $this->actingAs($this->admin())->post(route('crm.producers.store'), $this->producerForm([
            'email' => '', 'create_account' => '1', 'contact_first_name' => 'A', 'contact_last_name' => 'B',
        ]))->assertSessionHasErrors('email');
    }

    public function test_existing_buyer_account_is_linked_instead_of_duplicated(): void
    {
        $buyer = $this->visitor();
        $this->actingAs($this->admin())->post(route('crm.producers.store'), $this->producerForm([
            'email' => strtoupper($buyer->email), 'create_account' => '1', 'contact_first_name' => 'A', 'contact_last_name' => 'B',
        ]))->assertSessionHas('success');

        $this->assertSame($buyer->id, Producer::where('brand_name', 'Livada Test')->value('user_id'));
        $this->assertTrue($buyer->fresh()->hasRole('producer'));
        $this->assertSame(0, NotificationLog::where('template_key', 'PRODUCER_INVITE')->count(), 'are deja parolă: fără invitație');
    }

    public function test_invitation_sets_the_password_once_and_logs_in(): void
    {
        $this->actingAs($this->admin())->post(route('crm.producers.store'), $this->producerForm([
            'create_account' => '1', 'contact_first_name' => 'Bartha', 'contact_last_name' => 'Anna',
        ]));
        $user = Producer::where('brand_name', 'Livada Test')->firstOrFail()->user;
        $link = app(InvitationService::class)->link($user);
        auth()->logout();

        $this->get($link)->assertOk()->assertSee('Livada Test');
        $this->post($link, ['password' => 'ParolaNoua123', 'password_confirmation' => 'ParolaNoua123'])->assertRedirect(route('producer.profile'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);

        auth()->logout();
        $this->get($link)->assertRedirect(route('hu.login')); // folosit o dată → nu mai merge
        $this->post($link, ['password' => 'Alta12345', 'password_confirmation' => 'Alta12345'])->assertRedirect(route('hu.login'));
        $this->assertTrue(password_verify('ParolaNoua123', $user->fresh()->password));
    }

    public function test_invitation_expires_and_cannot_be_forged(): void
    {
        $user = User::factory()->withRole('visitor')->create();
        $link = app(InvitationService::class)->link($user);

        $other = User::factory()->withRole('visitor')->create();
        $this->get(str_replace('/invite/'.$user->id.'?', '/invite/'.$other->id.'?', $link))->assertForbidden(); // alt cont, aceeași semnătură
        $this->get(preg_replace('/signature=[a-f0-9]+/', 'signature=deadbeef', $link))->assertForbidden();
        $this->travel(8)->days();
        $this->get($link)->assertForbidden();
    }

    public function test_buyer_added_by_organizer_with_phone_only(): void
    {
        $this->actingAs($this->admin('organizer'))->post(route('crm.visitors.store'), [
            'first_name' => 'Ilona', 'last_name' => 'Kiss', 'phone' => '0741 111 222', 'preferred_language' => 'hu', 'consent_terms' => '1',
        ])->assertRedirect()->assertSessionHas('success');
        $this->post(route('crm.visitors.store'), [
            'first_name' => 'Ion', 'last_name' => 'Pop', 'phone' => '0741 333 444', 'preferred_language' => 'ro', 'consent_terms' => '1', 'newsletter' => '1',
        ])->assertSessionHas('success');

        $ilona = User::where('first_name', 'Ilona')->firstOrFail();
        $this->assertNull($ilona->email);
        $this->assertTrue($ilona->hasRole('visitor'));
        $this->assertMatchesRegularExpression('/^AM-/', $ilona->member_code);
        $this->assertSame(4, Consent::where('user_id', $ilona->id)->where('source', 'crm')->count());
        $this->assertTrue(User::where('first_name', 'Ion')->firstOrFail()->newsletter);
        $this->assertSame(0, NotificationLog::count(), 'fără e-mail: nimic de trimis');
    }

    public function test_staff_finds_a_buyer_by_phone_whatever_the_formatting(): void
    {
        User::factory()->withRole('visitor')->create(['first_name' => 'Ilona', 'email' => null, 'phone' => '0741 111 222']);

        $this->actingAs($this->admin('staff'))->getJson(route('staff.search', ['q' => '0741111222']))
            ->assertOk()->assertJsonPath('results.0.name', fn ($n) => str_contains($n, 'Ilona'));
        $this->actingAs($this->admin())->get(route('crm.visitors.index', ['q' => '0741-111-222']))->assertSee('Ilona');
    }

    public function test_buyer_needs_email_or_phone_and_consent(): void
    {
        $this->actingAs($this->admin())->post(route('crm.visitors.store'), [
            'first_name' => 'A', 'last_name' => 'B', 'preferred_language' => 'ro',
        ])->assertSessionHasErrors(['phone', 'consent_terms']);
    }

    public function test_buyer_with_email_gets_the_invitation_and_lands_on_his_card(): void
    {
        $this->actingAs($this->admin())->post(route('crm.visitors.store'), [
            'first_name' => 'Maria', 'last_name' => 'Pop', 'email' => 'maria@example.test', 'preferred_language' => 'ro',
            'consent_terms' => '1', 'send_invite' => '1',
        ])->assertSessionHas('success');
        $maria = User::where('email', 'maria@example.test')->firstOrFail();
        $this->assertSame(1, NotificationLog::where('template_key', 'ACCOUNT_INVITE')->count());

        auth()->logout();
        $link = app(InvitationService::class)->link($maria);
        $this->post($link, ['password' => 'Maria12345', 'password_confirmation' => 'Maria12345'])->assertRedirect(route('account.card'));
    }

    public function test_staff_cannot_add_buyers_or_producers(): void
    {
        $this->actingAs($this->admin('staff'));
        $this->post(route('crm.visitors.store'), ['first_name' => 'A'])->assertForbidden();
        $this->post(route('crm.producers.store'), $this->producerForm())->assertForbidden();
    }

    public function test_crm_pages_render(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('crm.producers.create'))->assertOk()->assertSee('Creează cont pentru producător');
        $this->get(route('crm.visitors.create'))->assertOk()->assertSee('Adaugă cumpărătorul');
        $this->get(route('crm.visitors.index'))->assertOk()->assertSee('Cumpărător nou');
    }
}
