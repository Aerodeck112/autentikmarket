<?php

namespace App\Services\Reservations;

use App\Enums\RegistrationStatus;
use App\Enums\ReservationStatus;
use App\Enums\SlotStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventSlot;
use App\Models\Producer;
use App\Models\StandReservation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Documents\DocumentService;
use App\Services\Notifications\NotificationService;
use App\Support\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rezervarea standurilor — sigură la concurență.
 *
 * Garanții (independente de frontend):
 *  1. tranzacție + SELECT … FOR UPDATE pe rândul standului;
 *  2. UNIQUE(stand_reservations.active_slot_id)  → un singur rând activ per stand;
 *  3. UNIQUE(stand_reservations.active_producer_key) → max. o rezervare activă / producător / eveniment.
 */
class StandReservationService
{
    public function __construct(protected NotificationService $notifications) {}

    /**
     * Pasul „ALEGE ACEST SPAȚIU”: creează un hold temporar (implicit 15 minute).
     */
    public function hold(Event $event, EventSlot $slot, Producer $producer): StandReservation
    {
        if (! $producer->isApproved()) {
            throw new BusinessRuleException(__('Doar producătorii aprobați pot rezerva standuri.'), 'producer_not_approved');
        }
        if (! $event->registrationsOpen()) {
            throw new BusinessRuleException(__('Înscrierile pentru acest târg nu sunt deschise.'), 'registrations_closed');
        }
        if ($slot->event_id !== $event->id) {
            throw new BusinessRuleException(__('Standul nu aparține acestui eveniment.'), 'wrong_event');
        }

        $this->expireHolds($event->id);

        try {
            return DB::transaction(function () use ($event, $slot, $producer) {
                /** @var EventSlot $locked */
                $locked = EventSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();

                $active = StandReservation::where('active_slot_id', $locked->id)->lockForUpdate()->first();
                if ($active && $active->status === ReservationStatus::Hold && $active->hold_expires_at?->isPast()) {
                    $this->deactivate($active, ReservationStatus::Expired, $locked);
                    $active = null;
                }
                if ($active) {
                    if ($active->producer_id === $producer->id) {
                        return $active; // re-click pe propriul hold: idempotent
                    }
                    throw new BusinessRuleException(__('Standul a fost deja selectat de alt producător.'), 'slot_taken');
                }

                // Statusuri „blocante” fără rezervare activă = blocat de admin.
                if (in_array($locked->status, [SlotStatus::Blocked, SlotStatus::Unavailable, SlotStatus::Reserved, SlotStatus::Occupied], true)) {
                    throw new BusinessRuleException(__('Standul nu este disponibil.'), 'slot_unavailable');
                }

                $registration = EventRegistration::firstOrCreate(
                    ['event_id' => $event->id, 'producer_id' => $producer->id],
                    ['status' => RegistrationStatus::Draft]
                );
                if (in_array($registration->status, [RegistrationStatus::Rejected], true)) {
                    throw new BusinessRuleException(__('Participarea ta la acest târg a fost respinsă.'), 'registration_rejected');
                }

                // Producătorul își schimbă alegerea: hold-ul anterior se eliberează.
                $previous = StandReservation::where('active_producer_key', $this->producerKey($event, $producer))->lockForUpdate()->first();
                if ($previous) {
                    if ($previous->status !== ReservationStatus::Hold) {
                        throw new BusinessRuleException(__('Ai deja o cerere de stand pentru acest târg.'), 'already_requested');
                    }
                    $this->deactivate($previous, ReservationStatus::Released);
                }

                $reservation = StandReservation::create([
                    'event_id' => $event->id,
                    'event_slot_id' => $locked->id,
                    'producer_id' => $producer->id,
                    'event_registration_id' => $registration->id,
                    'status' => ReservationStatus::Hold,
                    'active_slot_id' => $locked->id,
                    'active_producer_key' => $this->producerKey($event, $producer),
                    'hold_expires_at' => now()->addMinutes(Settings::holdMinutes()),
                    'price' => $locked->price,
                ]);

                $locked->update(['status' => SlotStatus::Hold]);

                return $reservation;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException(__('Standul a fost deja selectat de alt producător.'), 'slot_taken');
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new BusinessRuleException(__('Standul a fost deja selectat de alt producător.'), 'slot_taken');
            }
            throw $e;
        }
    }

    /**
     * Pasul „SOLICITARE”: hold → cerere trimisă (pending approval).
     *
     * @param  array{needs_electricity?:bool, needs_table?:bool, needs_tent?:bool, products_note?:string, notes?:string}  $needs
     */
    public function request(StandReservation $reservation, Producer $producer, array $needs = []): StandReservation
    {
        $reservation = DB::transaction(function () use ($reservation, $producer, $needs) {
            $r = StandReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($r->producer_id !== $producer->id) {
                throw new BusinessRuleException(__('Rezervare inexistentă.'), 'not_owner');
            }
            if ($r->status !== ReservationStatus::Hold) {
                throw new BusinessRuleException(__('Rezervarea nu mai poate fi trimisă.'), 'invalid_state');
            }
            if ($r->hold_expires_at?->isPast()) {
                $this->deactivate($r, ReservationStatus::Expired);
                throw new BusinessRuleException(__('Timpul de rezervare a expirat. Te rugăm să alegi din nou standul.'), 'hold_expired');
            }

            $r->update([
                'status' => ReservationStatus::Requested,
                'requested_at' => now(),
                'hold_expires_at' => null,
                'notes' => $needs['notes'] ?? null,
            ]);
            EventSlot::whereKey($r->event_slot_id)->update(['status' => SlotStatus::Requested->value]);

            $r->registration?->update([
                'status' => RegistrationStatus::Submitted,
                'submitted_at' => now(),
                'data_confirmed_at' => $r->registration->data_confirmed_at ?? now(),
                'needs_electricity' => (bool) ($needs['needs_electricity'] ?? false),
                'needs_table' => (bool) ($needs['needs_table'] ?? false),
                'needs_tent' => (bool) ($needs['needs_tent'] ?? false),
                'products_note' => $needs['products_note'] ?? null,
                'notes' => $needs['notes'] ?? null,
            ]);

            return $r;
        });

        AuditLogger::log('stand_reservation.requested', $reservation, [], ['slot' => $reservation->slot->code]);
        $this->notifications->send('EVENT_REGISTRATION_RECEIVED', $producer, $this->payload($reservation), [
            'related' => $reservation,
            'dedupe_key' => 'reservation-requested:'.$reservation->id,
        ]);

        return $reservation;
    }

    /** Pasul „ADMIN APROBĂ”. Generează automat anexa (dacă există template). */
    public function approve(StandReservation $reservation, User $admin, ?string $note = null): StandReservation
    {
        $reservation = DB::transaction(function () use ($reservation, $admin, $note) {
            $r = StandReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (! in_array($r->status, [ReservationStatus::Requested, ReservationStatus::Hold], true) || ! $r->isActive()) {
                throw new BusinessRuleException(__('Doar cererile active pot fi aprobate.'), 'invalid_state');
            }
            $old = $r->status;
            $r->update([
                'status' => ReservationStatus::Approved,
                'hold_expires_at' => null,
                'decided_at' => now(),
                'decided_by' => $admin->id,
                'admin_notes' => $note,
            ]);
            EventSlot::whereKey($r->event_slot_id)->update(['status' => SlotStatus::Reserved->value]);
            $r->registration?->update([
                'status' => RegistrationStatus::Approved,
                'decided_at' => now(),
                'decided_by' => $admin->id,
            ]);
            AuditLogger::log('stand_reservation.approved', $r, ['status' => $old], ['status' => ReservationStatus::Approved, 'slot' => $r->slot->code], $admin->id);

            return $r;
        });

        $annex = null;
        try {
            $annex = app(DocumentService::class)->generateAnnex($reservation->fresh(['registration', 'producer', 'event', 'slot']), $admin);
        } catch (Throwable $e) {
            Log::warning('Anexa nu a putut fi generată automat: '.$e->getMessage());
        }

        $this->notifications->send('RESERVATION_APPROVED', $reservation->producer, $this->payload($reservation), [
            'related' => $reservation,
            'dedupe_key' => 'reservation-approved:'.$reservation->id,
            'action_url' => route('producer.events.show', $reservation->event_id),
            'action_label_ro' => 'Vezi participarea',
            'action_label_hu' => 'Részvétel megtekintése',
        ]);

        return $reservation;
    }

    public function reject(StandReservation $reservation, User $admin, ?string $reason = null): StandReservation
    {
        $reservation = DB::transaction(function () use ($reservation, $admin, $reason) {
            $r = StandReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (! $r->isActive()) {
                throw new BusinessRuleException(__('Rezervarea nu mai este activă.'), 'invalid_state');
            }
            $old = $r->status;
            $this->deactivate($r, ReservationStatus::Rejected, null, ['decided_at' => now(), 'decided_by' => $admin->id, 'admin_notes' => $reason]);
            AuditLogger::log('stand_reservation.rejected', $r, ['status' => $old], ['status' => ReservationStatus::Rejected, 'reason' => $reason], $admin->id);

            return $r;
        });

        $this->notifications->send('RESERVATION_REJECTED', $reservation->producer, $this->payload($reservation) + ['reason' => (string) $reason], [
            'related' => $reservation,
            'dedupe_key' => 'reservation-rejected:'.$reservation->id,
        ]);

        return $reservation;
    }

    public function cancel(StandReservation $reservation, ?User $actor = null, ?string $reason = null): StandReservation
    {
        return DB::transaction(function () use ($reservation, $actor, $reason) {
            $r = StandReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (! $r->isActive()) {
                return $r;
            }
            $old = $r->status;
            $this->deactivate($r, ReservationStatus::Cancelled, null, ['admin_notes' => $reason]);
            AuditLogger::log('stand_reservation.cancelled', $r, ['status' => $old], ['status' => ReservationStatus::Cancelled, 'reason' => $reason], $actor?->id);

            return $r;
        });
    }

    /** Admin alocă direct un stand unui producător (ex. înscriere telefonică). */
    public function assign(Event $event, EventSlot $slot, Producer $producer, User $admin): StandReservation
    {
        try {
            $reservation = DB::transaction(function () use ($event, $slot, $producer, $admin) {
                $locked = EventSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
                if ($locked->event_id !== $event->id) {
                    throw new BusinessRuleException(__('Standul nu aparține acestui eveniment.'), 'wrong_event');
                }
                $active = StandReservation::where('active_slot_id', $locked->id)->lockForUpdate()->first();
                if ($active && $active->status === ReservationStatus::Hold && $active->hold_expires_at?->isPast()) {
                    $this->deactivate($active, ReservationStatus::Expired, $locked);
                    $active = null;
                }
                if ($active) {
                    throw new BusinessRuleException(__('Standul a fost deja selectat de alt producător.'), 'slot_taken');
                }
                if (in_array($locked->status, [SlotStatus::Blocked, SlotStatus::Unavailable], true)) {
                    throw new BusinessRuleException(__('Standul nu este disponibil.'), 'slot_unavailable');
                }
                $previous = StandReservation::where('active_producer_key', $this->producerKey($event, $producer))->lockForUpdate()->first();
                if ($previous) {
                    $this->deactivate($previous, ReservationStatus::Released);
                }
                $registration = EventRegistration::firstOrCreate(
                    ['event_id' => $event->id, 'producer_id' => $producer->id],
                    ['status' => RegistrationStatus::Draft]
                );
                $registration->update(['status' => RegistrationStatus::Approved, 'decided_at' => now(), 'decided_by' => $admin->id, 'submitted_at' => $registration->submitted_at ?? now()]);

                $r = StandReservation::create([
                    'event_id' => $event->id,
                    'event_slot_id' => $locked->id,
                    'producer_id' => $producer->id,
                    'event_registration_id' => $registration->id,
                    'status' => ReservationStatus::Approved,
                    'active_slot_id' => $locked->id,
                    'active_producer_key' => $this->producerKey($event, $producer),
                    'requested_at' => now(),
                    'decided_at' => now(),
                    'decided_by' => $admin->id,
                    'price' => $locked->price,
                ]);
                $locked->update(['status' => SlotStatus::Reserved]);
                AuditLogger::log('stand_reservation.assigned', $r, [], ['slot' => $locked->code, 'producer_id' => $producer->id], $admin->id);

                return $r;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException(__('Standul a fost deja selectat de alt producător.'), 'slot_taken');
        }

        return $reservation;
    }

    /** Blochează / deblochează un stand (doar dacă nu are rezervare activă). */
    public function setBlocked(EventSlot $slot, bool $blocked, User $admin): EventSlot
    {
        return DB::transaction(function () use ($slot, $blocked, $admin) {
            $locked = EventSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
            if ($blocked && StandReservation::where('active_slot_id', $locked->id)->exists()) {
                throw new BusinessRuleException(__('Standul are o rezervare activă și nu poate fi blocat.'), 'has_reservation');
            }
            $old = $locked->status;
            $locked->update(['status' => $blocked ? SlotStatus::Blocked : SlotStatus::Available]);
            AuditLogger::log('event_slot.'.($blocked ? 'blocked' : 'unblocked'), $locked, ['status' => $old], ['status' => $locked->status], $admin->id);

            return $locked;
        });
    }

    /**
     * Expiră hold-urile depășite. Rulat de scheduler (în fiecare minut) ȘI lazy la request.
     */
    public function expireHolds(?int $eventId = null): int
    {
        $ids = StandReservation::query()
            ->where('status', ReservationStatus::Hold->value)
            ->whereNotNull('active_slot_id')
            ->where('hold_expires_at', '<', now())
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            $count += DB::transaction(function () use ($id) {
                $r = StandReservation::whereKey($id)->lockForUpdate()->first();
                if (! $r || $r->status !== ReservationStatus::Hold || ! $r->isActive() || ! $r->hold_expires_at?->isPast()) {
                    return 0;
                }
                $this->deactivate($r, ReservationStatus::Expired);

                return 1;
            });
        }

        return $count;
    }

    // ---------------------------------------------------------------------

    /** Scoate rezervarea din starea activă și eliberează standul. */
    protected function deactivate(StandReservation $r, ReservationStatus $status, ?EventSlot $lockedSlot = null, array $extra = []): void
    {
        $r->update(array_merge([
            'status' => $status,
            'active_slot_id' => null,
            'active_producer_key' => null,
            'hold_expires_at' => null,
        ], $extra));

        $slot = $lockedSlot ?? EventSlot::whereKey($r->event_slot_id)->lockForUpdate()->first();
        if ($slot && in_array($slot->status, [SlotStatus::Hold, SlotStatus::Requested, SlotStatus::Reserved, SlotStatus::Occupied], true)) {
            $slot->update(['status' => SlotStatus::Available]);
        }
    }

    protected function producerKey(Event $event, Producer $producer): string
    {
        return $event->id.':'.$producer->id;
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true) || str_contains($e->getMessage(), 'UNIQUE');
    }

    protected function payload(StandReservation $r): array
    {
        $r->loadMissing(['event', 'slot', 'producer']);
        $lang = $r->producer->preferredLanguage()->value;

        return [
            'producer_name' => $r->producer->brand_name,
            'event_title' => $r->event->t('title', $lang),
            'event_date' => format_date_local($r->event->event_date, true, $lang),
            'stand_code' => $r->slot->code,
            'stand_size' => $r->slot->dimensions(),
        ];
    }
}
