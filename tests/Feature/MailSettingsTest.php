<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Support\MailSettings;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/** SMTP din CRM (fără .env) + test de e-mail imediat, cu eroarea explicată. */
class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function form(array $over = []): array
    {
        return array_merge([
            'smtp_host' => 'mail.autentikmarket.ro', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
            'smtp_username' => 'noreply@autentikmarket.ro', 'smtp_password' => 'Secret#123', 'smtp_verify_peer' => '1',
            'from_address' => 'noreply@autentikmarket.ro', 'from_name' => 'Autentik Market', 'action' => 'save',
        ], $over);
    }

    public function test_smtp_is_saved_in_the_crm_with_an_encrypted_password_and_applied(): void
    {
        $this->actingAs($this->admin())->put(route('crm.settings.mail'), $this->form())->assertSessionHas('success');

        $stored = Settings::get('mail.smtp_password');
        $this->assertNotSame('Secret#123', $stored, 'parola nu se păstrează în clar');
        $this->assertSame('Secret#123', MailSettings::password());
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('mail.autentikmarket.ro', config('mail.mailers.smtp.host'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));

        // parolă goală = se păstrează cea salvată; TLS → STARTTLS pe 587
        $this->put(route('crm.settings.mail'), $this->form(['smtp_password' => '', 'smtp_encryption' => 'tls', 'smtp_port' => 587]))->assertSessionHas('success');
        $this->assertSame('Secret#123', MailSettings::password());
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
    }

    public function test_save_and_test_sends_immediately(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('crm.settings.mail'), $this->form(['action' => 'test']))->assertSessionHas('success');

        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->hasTo($admin->email));
    }

    public function test_log_mode_is_reported_instead_of_a_fake_success(): void
    {
        config(['mail.default' => 'log']);

        $this->actingAs($this->admin())->post(route('crm.settings.test-email'))->assertSessionHas('error');
        $this->get(route('crm.dashboard'))->assertSee('E-mailurile NU se trimit');
    }

    public function test_crm_settings_can_be_cleared_back_to_env(): void
    {
        $this->actingAs($this->admin())->put(route('crm.settings.mail'), $this->form());
        $this->put(route('crm.settings.mail'), ['action' => 'clear'])->assertSessionHas('success');

        $this->assertFalse(MailSettings::fromCrm());
        $this->assertSame('', (string) Settings::get('mail.smtp_password'));
    }

    public function test_host_must_be_a_plain_server_name(): void
    {
        $this->actingAs($this->admin())->put(route('crm.settings.mail'), $this->form(['smtp_host' => 'https://mail.x.ro']))->assertSessionHasErrors('smtp_host');
    }

    public function test_server_errors_are_explained(): void
    {
        $this->assertStringContainsString('utilizatorul sau parola', MailSettings::explain(new RuntimeException('Failed to authenticate on SMTP server with username "x" using 2 possible authenticators. 535 Incorrect authentication data')));
        $this->assertStringContainsString('Certificatul', MailSettings::explain(new RuntimeException('stream_socket_enable_crypto(): Peer certificate CN=`server.host.ro\' did not match expected CN=`mail.x.ro\'')));
        $this->assertStringContainsString('portul', MailSettings::explain(new RuntimeException('Connection could not be established with host "ssl://mail.x.ro:465": Connection timed out')));
        $this->assertStringContainsString('expeditorul', MailSettings::explain(new RuntimeException('Expected response code "250" but got code "553", with message "553 sender is not owned by user"')));
    }

    public function test_only_settings_managers_can_change_smtp(): void
    {
        $this->actingAs($this->admin('organizer'))->put(route('crm.settings.mail'), $this->form())->assertForbidden();
    }
}
