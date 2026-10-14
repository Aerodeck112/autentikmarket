<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\Events\PostEventWorkflow;
use Illuminate\Console\Command;

class PostEventCatchupCommand extends Command
{
    protected $signature = 'events:post-event';

    protected $description = 'Rulează workflow-ul post-eveniment pentru evenimentele încheiate încă neprocesate (plasă de siguranță).';

    public function handle(PostEventWorkflow $workflow): int
    {
        Event::where('status', EventStatus::Completed->value)->whereNull('post_event_processed_at')
            ->get()->each(fn (Event $e) => $this->line($e->slug.': '.json_encode($workflow->run($e))));

        return self::SUCCESS;
    }
}
