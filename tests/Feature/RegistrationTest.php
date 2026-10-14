<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function payload(array $extra = []): array
    {
        return array_merge([
            'first_name' => 'István', 'last_name' => 'Kovács', 'email' => 'istvan@example.test',
            'phone' => '0740123456', 'city' => 'Marosvásárhely', 'preferred_language' => 'hu',
            'password' => 'Jelszo1234', 'password_confirmation' => 'Jelszo1234', 'terms' => '1', 'privacy' => '1',
            'newsletter' => '0', 'marketing' => '0',
        ], $extra);
    }

    public function test_preferred_language_is_required(): void
    {
        $this->post(route('ro.register.store'), $this->payload(['preferred_language' => '']))
            ->assertSessionHasErrors('preferred_language');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_stores_language_card_and_separate_consents(): void
    {
        Mail::fake();
        $this->post(route('hu.register.store'), $this->payload())->assertRedirect(route('account.card'));

        $user = User::where('email', 'istvan@example.test')->sole();
        $this->assertSame('hu', $user->preferredLanguage()->value);
        $this->assertMatchesRegularExpression('/^AM-[A-Z0-9]{6}$/', $user->member_code);
        $this->assertSame(40, strlen($user->card_token));
        $this->assertTrue($user->hasRole('visitor'));

        // Termenii acceptați NU implică marketing.
        $this->assertTrue(Consent::where('user_id', $user->id)->where('type', 'terms')->value('granted'));
        $this->assertFalse((bool) Consent::where('user_id', $user->id)->where('type', 'marketing')->value('granted'));
        $this->assertFalse($user->marketing_consent);
        $this->assertDatabaseHas('notification_logs', ['template_key' => 'ACCOUNT_WELCOME', 'language' => 'hu']);
    }

    public function test_hu_register_page_preselects_hungarian(): void
    {
        $this->get(route('hu.register'))->assertOk()->assertSee('value="hu" checked', false);
        $this->get(route('ro.register'))->assertOk()->assertSee('value="ro" checked', false);
    }

    public function test_producer_registration_creates_producer_with_language(): void
    {
        Mail::fake();
        $this->post(route('ro.register.producer.store'), $this->payload(['brand_name' => 'Méhészet Kovács', 'email' => 'mehesz@example.test']))
            ->assertRedirect(route('producer.profile'));
        $user = User::where('email', 'mehesz@example.test')->sole();
        $this->assertSame('hu', $user->producer->preferredLanguage()->value);
        $this->assertSame('new', $user->producer->status->value);
    }
}
