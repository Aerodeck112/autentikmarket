<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Services\Checkin\CheckinService;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Logare și identificarea clientului cu numărul de telefon. */
class PhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_numbers_are_normalized(): void
    {
        foreach (['0740 123 456', '+40 740-123-456', '0040740123456', '(0740) 123.456'] as $p) {
            $this->assertSame('40740123456', Phone::key($p), $p);
        }
        $this->assertSame('36301234567', Phone::key('06 30 123 4567'));
        $this->assertSame('36301234567', Phone::key('+36 30 123 4567'));
        $this->assertNull(Phone::key('123'));
        $this->assertFalse(Phone::looksLikePhone('ana@example.test'));
        $this->assertFalse(Phone::looksLikePhone('AM-ABC234'));
        $this->assertSame('https://wa.me/40740123456?text=Bun%C4%83', Phone::whatsappUrl('0740 123 456', 'Bună'));
    }

    public function test_user_can_log_in_with_phone_written_any_way(): void
    {
        $user = User::factory()->create(['phone' => '0740 123 456', 'password' => Hash::make('Parola123')]);
        $this->assertSame('40740123456', $user->phone_key);

        $this->post(lroute('login.store', [], 'ro'), ['email' => '+40740123456', 'password' => 'Parola123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_or_inactive_account_is_refused(): void
    {
        User::factory()->create(['phone' => '0740123456', 'password' => Hash::make('Parola123')]);
        User::factory()->create(['phone' => '0750000000', 'password' => Hash::make('Parola123'), 'is_active' => false]);

        $this->post(lroute('login.store', [], 'ro'), ['email' => '0740123456', 'password' => 'gresit'])->assertSessionHasErrors('email');
        $this->post(lroute('login.store', [], 'ro'), ['email' => '0750000000', 'password' => 'Parola123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_shared_phone_logs_in_the_account_whose_password_matches(): void
    {
        User::factory()->create(['phone' => '0740123456', 'password' => Hash::make('ParolaUnu1')]);
        $second = User::factory()->create(['phone' => '0740 123 456', 'password' => Hash::make('ParolaDoi2')]);

        $this->post(lroute('login.store', [], 'ro'), ['email' => '0740123456', 'password' => 'ParolaDoi2'])->assertRedirect();
        $this->assertAuthenticatedAs($second);
    }

    public function test_email_login_still_works(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.test', 'password' => Hash::make('Parola123')]);
        $this->post(lroute('login.store', [], 'ro'), ['email' => 'Ana@Example.test', 'password' => 'Parola123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_customer_is_identified_by_phone_at_the_stand_and_at_checkin(): void
    {
        $buyer = $this->visitor();
        $buyer->update(['phone' => '0740 999 888']);
        $service = app(CheckinService::class);

        $this->assertTrue($service->findUserByToken('0740999888')->is($buyer));
        $this->assertTrue($service->findUserByToken('+40 740 999 888')->is($buyer));
        $this->assertNull($service->findUserByToken('0740999'), 'număr incomplet: nimic');

        // același număr pe două conturi → nu se ghicește
        $this->visitor()->update(['phone' => '0740999888']);
        $this->assertNull($service->findUserByToken('0740999888'));

        // producătorul înregistrează cumpărătura introducând telefonul clientului
        $other = $this->visitor();
        $other->update(['phone' => '0751 222 333']);
        $producer = $this->approvedProducer();
        $event = Event::factory()->create(['status' => 'active', 'event_date' => today()->toDateString()]);
        $event->registrations()->create(['producer_id' => $producer->id, 'status' => 'approved']);
        $this->actingAs($producer->user)->postJson(route('producer.scan.store'), ['code' => '0751222333'])->assertOk()->assertJson(['ok' => true, 'new' => true]);
    }
}
