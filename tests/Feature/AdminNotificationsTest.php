<?php

namespace Tests\Feature;

use App\Enums\ProducerStatus;
use App\Models\NotificationLog;
use App\Models\Producer;
use App\Services\Accounts\InvitationService;
use App\Services\Producers\ProducerWorkflow;
use App\Services\Reservations\StandReservationService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Notificări către admin + super admin, regulamentul interior trimis producătorilor, linkuri WhatsApp. */
class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_are_notified_of_temporary_hold_and_stand_request(): void
    {
        $event = $this->openEvent(); // creează și un super admin
        $admin = $this->admin('admin');
        $admin->update(['preferred_language' => 'hu']);
        $organizer = $this->admin('organizer');
        $slot = $event->slots()->where('status', 'available')->first();
        $producer = $this->approvedProducer();
        $service = app(StandReservationService::class);

        $r = $service->hold($event, $slot, $producer);
        $service->hold($event, $slot, $producer); // re-click: fără mesaj nou
        $holds = NotificationLog::where('template_key', 'ADMIN_STAND_HOLD')->get();
        $this->assertCount(2, $holds, 'admin + super admin, o singură dată');
        $this->assertFalse($holds->contains('notifiable_id', $organizer->id), 'organizatorul nu e în listă');
        $this->assertSame('hu', $holds->firstWhere('notifiable_id', $admin->id)->language);
        $this->assertStringContainsString($slot->code, $holds->first()->subject);

        $service->request($r, $producer);
        $this->assertSame(2, NotificationLog::where('template_key', 'ADMIN_STAND_REQUEST')->count());
    }

    public function test_admins_are_notified_when_a_producer_signs_up(): void
    {
        $super = $this->admin();
        $this->post(lroute('register.producer.store', [], 'ro'), [
            'brand_name' => 'Ferma Nouă', 'first_name' => 'Ion', 'last_name' => 'Pop', 'email' => 'ion@example.test', 'phone' => '0740123456',
            'city' => 'Reghin', 'county' => 'Mureș', 'preferred_language' => 'ro', 'password' => 'ParolaBuna123', 'password_confirmation' => 'ParolaBuna123',
            'terms' => '1', 'privacy' => '1',
        ])->assertRedirect();

        $log = NotificationLog::where('template_key', 'ADMIN_PRODUCER_REGISTERED')->firstOrFail();
        $this->assertSame($super->id, $log->notifiable_id);
        $this->assertStringContainsString('Ferma Nouă', $log->subject);
        $this->assertStringContainsString('0740123456', $log->body);
        $this->assertSame('Mureș', Producer::where('brand_name', 'Ferma Nouă')->value('county'));
    }

    public function test_internal_rules_link_is_sent_to_producers_when_set(): void
    {
        Storage::fake('public');
        $producer = $this->approvedProducer();

        // fără regulament setat: niciun link
        app(InvitationService::class)->send($producer->user, $producer);
        $this->assertStringNotContainsString('Regulament interior', NotificationLog::latest('id')->first()->body);

        $this->actingAs($this->admin())->put(route('crm.settings.update'), [
            'reservations_hold_minutes' => 15, 'contracts_number_prefix' => 'AM',
            'rules_file' => UploadedFile::fake()->create('regulament.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $url = Settings::rulesUrl();
        $this->assertNotNull($url);
        Storage::disk('public')->assertExists(Settings::get('documents.rules_path'));

        // aprobarea (și invitația) conțin linkul; alte mesaje nu
        $other = Producer::factory()->create();
        app(ProducerWorkflow::class)->changeStatus($other, ProducerStatus::Approved, $this->admin());
        $approved = NotificationLog::where('template_key', 'PRODUCER_APPROVED')->latest('id')->firstOrFail();
        $this->assertStringContainsString($url, $approved->body);
        $this->actingAs($other->user)->get(route('producer.dashboard'))->assertOk()->assertSee('Regulament interior');
        $this->get(lroute('for-producers', [], 'ro'))->assertSee($url, false);
    }

    public function test_whatsapp_links_in_crm(): void
    {
        $buyer = $this->visitor();
        $buyer->update(['phone' => '0740 123 456']);
        $this->actingAs($this->admin())->get(route('crm.visitors.show', $buyer))->assertSee('https://wa.me/40740123456', false);
        $producer = $this->approvedProducer();
        $producer->update(['phone' => '+36 30 123 4567']);
        $this->get(route('crm.producers.show', $producer))->assertSee('https://wa.me/36301234567', false);
    }
}
