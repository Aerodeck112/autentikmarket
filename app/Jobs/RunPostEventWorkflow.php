<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Events\PostEventWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunPostEventWorkflow implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $eventId) {}

    public function handle(PostEventWorkflow $workflow): void
    {
        if ($event = Event::find($this->eventId)) {
            $workflow->run($event);
        }
    }
}
