<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Enums\ReservationStatus;
use App\Enums\SlotStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\RunPostEventWorkflow;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Tasks\TaskGenerator;
use Illuminate\Support\Facades\DB;

class EventLifecycleService
{
    /** Tranziții permise (orice → cancelled e permis, mai puțin din completed). */
    public const TRANSITIONS = [
        'draft' => ['planning', 'registrations_open', 'cancelled'],
        'planning' => ['draft', 'registrations_open', 'cancelled'],
        'registrations_open' => ['planning', 'registrations_closed', 'active', 'cancelled'],
        'registrations_closed' => ['registrations_open', 'active', 'completed', 'cancelled'],
        'active' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => ['draft'],
    ];

    public function __construct(
        protected LayoutSnapshotService $snapshots,
        protected TaskGenerator $tasks,
    ) {}

    public function create(array $data, ?User $admin = null): Event
    {
        return DB::transaction(function () use ($data, $admin) {
            $event = new Event($data);
            $event->created_by = $admin?->id;
            $event->edition ??= Event::nextEdition($event->venue_id, $event->city);
            $event->save();

            if ($event->venue_id && ($master = $event->venue?->masterLayout)) {
                $this->snapshots->snapshot($event, $master);
            }
            $this->tasks->generateForEvent($event, 'pre_event');

            AuditLogger::log('event.created', $event, [], ['title' => $event->title_ro, 'date' => $event->event_date->toDateString()], $admin?->id);

            return $event;
        });
    }

    public function canTransition(Event $event, EventStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$event->status->value] ?? [], true);
    }

    public function changeStatus(Event $event, EventStatus $to, ?User $admin = null): Event
    {
        if ($event->status === $to) {
            return $event;
        }
        if (! $this->canTransition($event, $to)) {
            throw new BusinessRuleException(__('Tranziție de status nepermisă: :from → :to', [
                'from' => $event->status->label(), 'to' => $to->label(),
            ]), 'invalid_transition');
        }

        $from = $event->status;
        DB::transaction(function () use ($event, $to) {
            $event->forceFill(['status' => $to]);
            if ($to === EventStatus::Completed) {
                $event->completed_at = now();
            }
            $event->save();

            if ($to === EventStatus::Active) {
                // Standurile aprobate devin „ocupate” în ziua târgului.
                EventSlot::where('event_id', $event->id)
                    ->whereIn('id', $event->reservations()->where('status', ReservationStatus::Approved->value)->select('event_slot_id'))
                    ->update(['status' => SlotStatus::Occupied->value]);
            }
        });

        AuditLogger::log('event.status_changed', $event, ['status' => $from], ['status' => $to], $admin?->id);

        if ($to === EventStatus::Completed) {
            RunPostEventWorkflow::dispatch($event->id);
        }

        return $event;
    }

    /**
     * Automatizare zilnică: deschide/închide înscrierile după date și marchează
     * evenimentele trecute ca „completed” (ceea ce pornește workflow-ul post-eveniment).
     */
    public function autoAdvance(): array
    {
        $changed = ['opened' => 0, 'closed' => 0, 'active' => 0, 'completed' => 0];
        $now = now();

        Event::where('status', EventStatus::Planning->value)
            ->whereNotNull('registration_start')->where('registration_start', '<=', $now)
            ->where(fn ($q) => $q->whereNull('registration_end')->orWhere('registration_end', '>', $now))
            ->get()->each(function (Event $e) use (&$changed) {
                $this->changeStatus($e, EventStatus::RegistrationsOpen);
                $changed['opened']++;
            });

        Event::where('status', EventStatus::RegistrationsOpen->value)
            ->whereNotNull('registration_end')->where('registration_end', '<', $now)
            ->get()->each(function (Event $e) use (&$changed) {
                $this->changeStatus($e, EventStatus::RegistrationsClosed);
                $changed['closed']++;
            });

        Event::whereIn('status', [EventStatus::RegistrationsOpen->value, EventStatus::RegistrationsClosed->value])
            ->whereDate('event_date', $now->toDateString())
            ->get()->each(function (Event $e) use (&$changed) {
                $this->changeStatus($e, EventStatus::Active);
                $changed['active']++;
            });

        Event::whereIn('status', [EventStatus::Active->value, EventStatus::RegistrationsClosed->value])
            ->whereDate('event_date', '<', $now->toDateString())
            ->get()->each(function (Event $e) use (&$changed) {
                $this->changeStatus($e, EventStatus::Completed);
                $changed['completed']++;
            });

        return $changed;
    }
}
