<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
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
            // Ediție din trecut (adăugată retroactiv) → direct în arhivă: încheiată, fără task-uri și fără e-mailuri.
            $past = self::isPast($event);
            if ($past) {
                $this->markArchived($event);
            }
            $event->save();

            if ($event->venue_id && ($master = $event->venue?->masterLayout)) {
                $this->snapshots->snapshot($event, $master);
            }
            if (! $past) {
                $this->tasks->generateForEvent($event, 'pre_event');
            }

            AuditLogger::log($past ? 'event.created_past' : 'event.created', $event, [], ['title' => $event->title_ro, 'date' => $event->event_date->toDateString()], $admin?->id);

            return $event;
        });
    }

    /** Data ediției a trecut (înainte de azi). */
    public static function isPast(Event $event): bool
    {
        return $event->event_date !== null && $event->event_date->lt(today());
    }

    /** Poate fi trecută în arhivă (ediție trecută care n-a parcurs fluxul normal)? */
    public static function canArchive(Event $event): bool
    {
        // Active / Înscrieri închise se încheie singure (cu mulțumiri): acelea nu se arhivează.
        return self::isPast($event) && in_array($event->status, [EventStatus::Draft, EventStatus::Planning, EventStatus::RegistrationsOpen], true);
    }

    /**
     * Ediție din trecut → „Încheiat” FĂRĂ fluxul de după târg (nu trimite mulțumiri sau cereri de recenzie
     * pentru o ediție de acum luni/ani și nu generează task-uri).
     */
    public function archive(Event $event, ?User $admin = null): Event
    {
        if (! self::canArchive($event)) {
            throw new BusinessRuleException(__('Doar o ediție cu data în trecut, aflată încă în ciornă, planificare sau cu înscrieri deschise, poate fi trecută în arhivă.'), 'invalid_transition');
        }
        $from = $event->status;
        $this->markArchived($event);
        $event->save();
        AuditLogger::log('event.archived', $event, ['status' => $from], ['status' => $event->status], $admin?->id);

        return $event;
    }

    protected function markArchived(Event $event): void
    {
        $event->forceFill([
            'status' => EventStatus::Completed,
            'completed_at' => $event->event_date->copy()->endOfDay(),
            'post_event_processed_at' => now(),
            'archived_at' => now(),
        ]);
    }

    /**
     * Producătorii prezenți la o ediție trecută (adăugați retroactiv): înscriere aprobată + prezent.
     * Debifarea șterge doar înscrierile fără rezervări de stand.
     *
     * @param  array<int, int|string>  $producerIds
     */
    public function syncPastParticipants(Event $event, array $producerIds, ?User $admin = null): void
    {
        if (! self::isPast($event)) {
            return;
        }
        $ids = collect($producerIds)->map(fn ($id) => (int) $id)->filter()->unique();
        DB::transaction(function () use ($event, $ids, $admin) {
            $existing = $event->registrations()->get()->keyBy('producer_id');
            foreach ($ids as $id) {
                $reg = $existing->get($id);
                if (! $reg) {
                    $event->registrations()->create([
                        'producer_id' => $id, 'status' => RegistrationStatus::Approved, 'attended' => true,
                        'decided_at' => $event->event_date, 'decided_by' => $admin?->id,
                        'admin_notes' => __('Adăugat retroactiv'),
                    ]);
                } elseif ($reg->status !== RegistrationStatus::Approved || $reg->attended !== true) {
                    $reg->update(['status' => RegistrationStatus::Approved, 'attended' => true]);
                }
            }
            $existing->reject(fn ($reg) => $ids->contains((int) $reg->producer_id))
                ->filter(fn ($reg) => $reg->status === RegistrationStatus::Approved && ! $reg->reservations()->exists())
                ->each->delete();
        });
        AuditLogger::log('event.past_participants', $event, [], ['producers' => $ids->count()], $admin?->id);
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
