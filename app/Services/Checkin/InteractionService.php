<?php

namespace App\Services\Checkin;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Event;
use App\Models\Producer;
use App\Models\ProducerCustomerInteraction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Relația CLIENT → PRODUCĂTOR → EVENIMENT („Am cumpărat de aici”).
 * Nu este management financiar: servește recenziilor relevante și statisticilor.
 */
class InteractionService
{
    /** Evenimentul „curent” al unui producător: azi, cu participare aprobată. */
    public function currentEventFor(Producer $producer): ?Event
    {
        return Event::query()
            ->whereDate('event_date', now()->toDateString())
            ->whereNotIn('status', [EventStatus::Draft->value, EventStatus::Cancelled->value])
            ->whereHas('registrations', fn ($q) => $q->where('producer_id', $producer->id)->where('status', RegistrationStatus::Approved->value))
            ->first();
    }

    public function record(Event $event, Producer $producer, User $user, string $source): ProducerCustomerInteraction
    {
        $participates = $event->registrations()
            ->where('producer_id', $producer->id)
            ->where('status', RegistrationStatus::Approved->value)
            ->exists();
        if (! $participates) {
            throw new BusinessRuleException(__('Producătorul nu participă la acest eveniment.'), 'producer_not_participating');
        }
        if ($producer->user_id && $producer->user_id === $user->id) {
            throw new BusinessRuleException(__('Nu poți înregistra o cumpărătură la propriul stand.'), 'self_interaction');
        }

        try {
            return ProducerCustomerInteraction::firstOrCreate(
                ['event_id' => $event->id, 'producer_id' => $producer->id, 'user_id' => $user->id],
                ['source' => $source]
            );
        } catch (UniqueConstraintViolationException) {
            return ProducerCustomerInteraction::where(['event_id' => $event->id, 'producer_id' => $producer->id, 'user_id' => $user->id])->firstOrFail();
        }
    }
}
