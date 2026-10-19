<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Services\Notifications\NotificationService;
use App\Support\QueueRunner;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Hosting partajat: proc_open e des dezactivat, deci nicio sarcină programată nu are voie să pornească
 * un proces separat (Schedule::command); coada se procesează în același proces sau din CRM.
 */
class QueueOnSharedHostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_scheduled_task_spawns_a_separate_process(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            $this->assertInstanceOf(CallbackEvent::class, $event, "„{$event->description}” ar porni un proces separat (proc_open)");
        }
        $names = collect($events)->pluck('description')->all();
        $this->assertContains('queue-processing', $names);
        $this->assertContains('scheduler-heartbeat', $names);
    }

    protected function queueOne(): void
    {
        config(['queue.default' => 'database']);
        Mail::fake();
        app(NotificationService::class)->send('ACCOUNT_WELCOME', $this->visitor(), ['name' => 'Test'], ['dedupe_key' => 'q-'.uniqid()]);
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_queue_runner_processes_the_queue_in_process(): void
    {
        $this->queueOne();

        $r = QueueRunner::run(5);

        $this->assertSame(1, $r['processed']);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame('sent', NotificationLog::latest('id')->first()->status->value);
        $this->assertSame($r, QueueRunner::lastRun());
    }

    public function test_send_now_button_empties_the_queue(): void
    {
        $this->queueOne();

        $this->actingAs($this->admin())->post(route('crm.automations.queue.run'))->assertSessionHas('success');

        $this->assertSame(0, DB::table('jobs')->count());
        $this->get(route('crm.automations.index'))->assertOk()->assertSee('Trimite acum mesajele din coadă');
    }

    public function test_send_now_requires_permission(): void
    {
        $this->actingAs($this->admin('staff'))->post(route('crm.automations.queue.run'))->assertForbidden();
    }
}
