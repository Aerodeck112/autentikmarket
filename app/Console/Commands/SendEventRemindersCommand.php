<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;

/**
 * Remindere eveniment (în limba fiecărui destinatar):
 *  - producători cu participare aprobată: cu 3 zile înainte;
 *  - vizitatori cu acord newsletter/marketing: cu 2 zile înainte.
 */
class SendEventRemindersCommand extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Trimite reminderele de eveniment către producători și vizitatori.';

    public function handle(NotificationService $notifications): int
    {
        $sent = 0;

        $producerEvents = Event::whereDate('event_date', now()->addDays(3)->toDateString())
            ->whereNotIn('status', [EventStatus::Draft->value, EventStatus::Cancelled->value])->get();
        foreach ($producerEvents as $event) {
            foreach ($event->approvedRegistrations()->with(['producer', 'activeReservation.slot'])->get() as $reg) {
                $lang = $reg->producer->preferredLanguage()->value;
                $sent += (int) (bool) $notifications->send('EVENT_REMINDER_PRODUCER', $reg->producer, [
                    'event_title' => $event->t('title', $lang),
                    'event_date' => format_date_local($event->event_date, true, $lang),
                    'event_time' => $event->timeRange(),
                    'event_address' => trim(($event->address ?? '').', '.$event->city, ', '),
                    'stand_code' => $reg->activeReservation?->slot?->code ?? '—',
                ], ['related' => $event, 'dedupe_key' => "reminder:event:{$event->id}:producer:{$reg->producer_id}"]);
            }
        }

        $visitorEvents = Event::whereDate('event_date', now()->addDays(2)->toDateString())
            ->whereNotIn('status', [EventStatus::Draft->value, EventStatus::Cancelled->value])->get();
        foreach ($visitorEvents as $event) {
            User::query()->where('is_active', true)->whereNull('anonymized_at')
                ->where(fn ($q) => $q->where('newsletter', true)->orWhere('marketing_consent', true))
                ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['producer']))
                ->chunkById(200, function ($users) use ($event, $notifications, &$sent) {
                    foreach ($users as $user) {
                        $lang = $user->preferredLanguage()->value;
                        $sent += (int) (bool) $notifications->send('EVENT_REMINDER_VISITOR', $user, [
                            'event_title' => $event->t('title', $lang),
                            'event_date' => format_date_local($event->event_date, true, $lang),
                            'event_time' => $event->timeRange(),
                            'event_address' => trim(($event->address ?? '').', '.$event->city, ', '),
                        ], [
                            'related' => $event,
                            'dedupe_key' => "reminder:event:{$event->id}:user:{$user->id}",
                            'action_url' => lroute('events.show', $event->slug, $lang),
                            'action_label_ro' => 'Detalii eveniment',
                            'action_label_hu' => 'Esemény részletei',
                        ]);
                    }
                });
            $event->forceFill(['reminders_sent_at' => now()])->save();
        }

        $this->info("Remindere trimise: {$sent}");

        return self::SUCCESS;
    }
}
