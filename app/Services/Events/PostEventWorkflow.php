<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ProducerCustomerInteraction;
use App\Services\Blog\BlogDraftGenerator;
use App\Services\Notifications\NotificationService;
use App\Services\Tasks\TaskGenerator;
use Illuminate\Support\Facades\URL;

/**
 * Workflow-ul post-eveniment (pornit când evenimentul devine COMPLETED).
 * Idempotent: poate rula de mai multe ori fără a trimite mesaje duble (dedupe_key + flag-uri).
 */
class PostEventWorkflow
{
    public function __construct(
        protected NotificationService $notifications,
        protected TaskGenerator $tasks,
        protected BlogDraftGenerator $blog,
    ) {}

    public function run(Event $event): array
    {
        if ($event->status !== EventStatus::Completed) {
            return [];
        }

        $result = ['producers' => 0, 'visitors' => 0, 'review_requests' => 0, 'tasks' => 0, 'blog' => false];

        // PRODUCĂTORI — mulțumire în limba lor
        EventRegistration::with('producer')
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::Approved->value)
            ->where(fn ($q) => $q->whereNull('attended')->orWhere('attended', true))
            ->whereNull('thanked_at')
            ->chunkById(100, function ($regs) use ($event, &$result) {
                foreach ($regs as $reg) {
                    $lang = $reg->producer->preferredLanguage()->value;
                    $this->notifications->send('EVENT_THANK_YOU_PRODUCER', $reg->producer, [
                        'event_title' => $event->t('title', $lang),
                        'event_date' => format_date_local($event->event_date, false, $lang),
                    ], ['related' => $event, 'dedupe_key' => "thanks:event:{$event->id}:producer:{$reg->producer_id}"]);
                    $reg->update(['thanked_at' => now()]);
                    $result['producers']++;
                }
            });

        // VIZITATORI — mulțumire în limba lor
        Checkin::with('user')->where('event_id', $event->id)->whereNull('thanked_at')
            ->chunkById(200, function ($checkins) use ($event, &$result) {
                foreach ($checkins as $checkin) {
                    $lang = $checkin->user->preferredLanguage()->value;
                    $this->notifications->send('EVENT_THANK_YOU_VISITOR', $checkin->user, [
                        'event_title' => $event->t('title', $lang),
                        'event_date' => format_date_local($event->event_date, false, $lang),
                        'visits' => $checkin->user->checkins()->count(),
                    ], [
                        'related' => $event,
                        'dedupe_key' => "thanks:event:{$event->id}:user:{$checkin->user_id}",
                        'action_url' => route('account.card'),
                        'action_label_ro' => 'Cardul meu Autentik',
                        'action_label_hu' => 'Autentik kártyám',
                    ]);
                    $checkin->update(['thanked_at' => now()]);
                    $result['visitors']++;
                }
            });

        // RECENZII — doar unde există interacțiune cumpărător–producător
        ProducerCustomerInteraction::with(['user', 'producer'])
            ->where('event_id', $event->id)->whereNull('review_requested_at')
            ->chunkById(200, function ($items) use ($event, &$result) {
                foreach ($items as $i) {
                    $lang = $i->user->preferredLanguage()->value;
                    $url = URL::temporarySignedRoute('reviews.create', now()->addDays(30), ['interaction' => $i->id]);
                    $this->notifications->send('REVIEW_REQUEST', $i->user, [
                        'producer_name' => $i->producer->brand_name,
                        'event_title' => $event->t('title', $lang),
                        'review_url' => $url,
                    ], [
                        'related' => $i,
                        'dedupe_key' => "review-request:{$i->id}",
                        'action_url' => $url,
                        'action_label_ro' => 'Lasă o recenzie',
                        'action_label_hu' => 'Értékelés írása',
                    ]);
                    $i->update(['review_requested_at' => now()]);
                    $result['review_requests']++;
                }
            });

        // TASK-URI post-eveniment
        $result['tasks'] = $this->tasks->generateForEvent($event, 'post_event');

        // BLOG — draft (niciodată publicat automat)
        if (! $event->blogPosts()->exists()) {
            $this->blog->generate($event);
            $result['blog'] = true;
        }

        $event->forceFill(['post_event_processed_at' => now()])->save();

        return $result;
    }
}
