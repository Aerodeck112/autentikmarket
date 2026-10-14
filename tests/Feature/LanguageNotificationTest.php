<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Mail\TemplatedMail;
use App\Models\Event;
use App\Models\NotificationTemplate;
use App\Services\Checkin\CheckinService;
use App\Services\Events\EventLifecycleService;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LanguageNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_romanian_client_receives_romanian_message(): void
    {
        Mail::fake();
        $user = $this->visitor('ro');
        $tpl = NotificationTemplate::where('key', 'EVENT_THANK_YOU_VISITOR')->first();

        $log = app(NotificationService::class)->send('EVENT_THANK_YOU_VISITOR', $user, ['event_title' => 'X', 'event_date' => 'Y', 'visits' => 3]);

        $this->assertSame('ro', $log->language);
        $this->assertSame($tpl->subject_ro, $log->subject);
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->mailSubject === $tpl->subject_ro && $m->hasTo($user->email));
        Mail::assertNotSent(TemplatedMail::class, fn ($m) => $m->mailSubject === $tpl->subject_hu);
    }

    public function test_hungarian_client_receives_hungarian_message(): void
    {
        Mail::fake();
        $user = $this->visitor('hu');
        $tpl = NotificationTemplate::where('key', 'EVENT_THANK_YOU_VISITOR')->first();

        $log = app(NotificationService::class)->send('EVENT_THANK_YOU_VISITOR', $user, ['event_title' => 'X', 'event_date' => 'Y', 'visits' => 3]);

        $this->assertSame('hu', $log->language);
        $this->assertSame($tpl->subject_hu, $log->subject);
        $this->assertStringContainsString('Köszönjük', $log->body);
        $this->assertStringNotContainsString('Îți mulțumim', $log->body);
        Mail::assertSent(TemplatedMail::class, fn ($m) => $m->mailSubject === $tpl->subject_hu);
    }

    public function test_romanian_producer_receives_romanian_message(): void
    {
        Mail::fake();
        $producer = $this->approvedProducer('ro');
        $log = app(NotificationService::class)->send('PRODUCER_APPROVED', $producer, ['producer_name' => $producer->brand_name]);

        $this->assertSame('ro', $log->language);
        $this->assertStringStartsWith('Felicitări!', $log->subject);
    }

    public function test_hungarian_producer_receives_hungarian_message(): void
    {
        Mail::fake();
        // Utilizatorul reprezentant are limba RO, dar producătorul HU → contează limba PRODUCĂTORULUI.
        $producer = $this->approvedProducer('hu');
        $producer->user->update(['preferred_language' => 'ro']);
        $log = app(NotificationService::class)->send('PRODUCER_APPROVED', $producer, ['producer_name' => $producer->brand_name]);

        $this->assertSame('hu', $log->language);
        $this->assertStringStartsWith('Gratulálunk!', $log->subject);
    }

    public function test_placeholders_are_escaped_in_html(): void
    {
        Mail::fake();
        $user = $this->visitor('ro');
        $log = app(NotificationService::class)->send('EVENT_THANK_YOU_VISITOR', $user, ['event_title' => '<script>alert(1)</script>', 'event_date' => 'Y', 'visits' => 1]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $log->body);
        $this->assertStringContainsString('&lt;script&gt;', $log->body);
    }

    public function test_dedupe_key_prevents_duplicate_messages(): void
    {
        Mail::fake();
        $user = $this->visitor('ro');
        $svc = app(NotificationService::class);
        $svc->send('ACCOUNT_WELCOME', $user, ['member_code' => 'x'], ['dedupe_key' => 'welcome:test']);
        $svc->send('ACCOUNT_WELCOME', $user, ['member_code' => 'x'], ['dedupe_key' => 'welcome:test']);

        Mail::assertSentCount(1);
    }

    public function test_post_event_thanks_are_sent_in_each_language(): void
    {
        Mail::fake();
        $event = Event::factory()->create(['event_date' => now()->subDay()->toDateString()]);
        $event->forceFill(['status' => 'active'])->save();
        $ro = $this->visitor('ro');
        $hu = $this->visitor('hu');
        foreach ([$ro, $hu] as $u) {
            app(CheckinService::class)->checkIn($event, $u);
        }

        app(EventLifecycleService::class)->changeStatus($event, EventStatus::Completed);

        $this->assertDatabaseHas('notification_logs', ['template_key' => 'EVENT_THANK_YOU_VISITOR', 'notifiable_id' => $ro->id, 'language' => 'ro']);
        $this->assertDatabaseHas('notification_logs', ['template_key' => 'EVENT_THANK_YOU_VISITOR', 'notifiable_id' => $hu->id, 'language' => 'hu']);
        $this->assertNotNull($event->fresh()->post_event_processed_at);
        $this->assertDatabaseHas('blog_posts', ['event_id' => $event->id, 'status' => 'draft']);
        $this->assertTrue($event->tasks()->where('phase', 'post_event')->exists());
    }
}
