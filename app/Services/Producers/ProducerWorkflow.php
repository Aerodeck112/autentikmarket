<?php

namespace App\Services\Producers;

use App\Enums\DocumentType;
use App\Enums\ProducerStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Producer;
use App\Models\ProducerDocumentRequest;
use App\Models\ProducerStatusHistory;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Workflow verificare producători:
 *   NOU → DATE INCOMPLETE → DOCUMENTE TRIMISE → ÎN VERIFICARE → APROBAT | RESPINS
 * Fiecare tranziție: cine, când, notă, istoric + audit + notificare în limba producătorului.
 */
class ProducerWorkflow
{
    public const REQUIRED_FIELDS = [
        'brand_name', 'legal_name', 'cui', 'representative_name', 'phone', 'email', 'city', 'producer_category_id', 'description_ro',
    ];

    public function __construct(protected NotificationService $notifications) {}

    /** @return list<string> câmpurile obligatorii lipsă */
    public function missingFields(Producer $p): array
    {
        $missing = [];
        foreach (self::REQUIRED_FIELDS as $f) {
            if (blank($p->$f)) {
                $missing[] = $f;
            }
        }
        if ($p->documents()->count() === 0) {
            $missing[] = 'documents';
        }

        return $missing;
    }

    /** Producătorul trimite profilul + documentele spre verificare. */
    public function submit(Producer $producer, User $actor): Producer
    {
        if (in_array($producer->status, [ProducerStatus::Approved, ProducerStatus::InReview], true)) {
            throw new BusinessRuleException(__('Profilul este deja în verificare sau aprobat.'), 'invalid_state');
        }
        $missing = $this->missingFields($producer);
        if ($missing) {
            $this->transition($producer, ProducerStatus::Incomplete, $actor, 'Lipsă: '.implode(', ', $missing));
            throw new BusinessRuleException(__('Profilul este incomplet. Completează câmpurile obligatorii și încarcă cel puțin un document.'), 'incomplete');
        }

        $this->transition($producer, ProducerStatus::DocumentsSubmitted, $actor, null);
        $producer->documentRequests()->whereNull('fulfilled_at')->update(['fulfilled_at' => now()]);

        // Notificare internă către cei care verifică producători.
        $reviewers = User::whereHas('roles.permissions', fn ($q) => $q->where('name', 'producers.approve'))->where('is_active', true)->get();
        foreach ($reviewers as $r) {
            $this->notifications->send('ADMIN_NEW_PRODUCER', $r, ['producer_name' => $producer->brand_name, 'city' => (string) $producer->city], [
                'related' => $producer,
                'dedupe_key' => "admin-new-producer:{$producer->id}:{$r->id}:".now()->format('YmdH'),
                'action_url' => route('crm.producers.show', $producer),
                'action_label_ro' => 'Verifică producătorul',
                'action_label_hu' => 'Termelő ellenőrzése',
            ]);
        }

        return $producer;
    }

    public function changeStatus(Producer $producer, ProducerStatus $to, User $actor, ?string $note = null): Producer
    {
        $from = $producer->status;
        $this->transition($producer, $to, $actor, $note);

        if ($to === ProducerStatus::Approved && $from !== ProducerStatus::Approved) {
            AuditLogger::log('producer.approved', $producer, ['status' => $from], ['status' => $to, 'note' => $note], $actor->id);
            $this->notifications->send('PRODUCER_APPROVED', $producer, ['producer_name' => $producer->brand_name], [
                'related' => $producer,
                'dedupe_key' => 'producer-approved:'.$producer->id.':'.now()->format('YmdHi'),
                'action_url' => route('producer.events'),
                'action_label_ro' => 'Vezi următoarele târguri',
                'action_label_hu' => 'A következő vásárok',
            ]);
        } elseif ($to === ProducerStatus::Rejected) {
            AuditLogger::log('producer.rejected', $producer, ['status' => $from], ['status' => $to, 'note' => $note], $actor->id);
            $this->notifications->send('PRODUCER_REJECTED', $producer, [
                'producer_name' => $producer->brand_name,
                'reason' => $note ?: '—',
            ], ['related' => $producer, 'dedupe_key' => 'producer-rejected:'.$producer->id.':'.now()->format('YmdHi')]);
        } else {
            AuditLogger::log('producer.status_changed', $producer, ['status' => $from], ['status' => $to, 'note' => $note], $actor->id);
        }

        return $producer;
    }

    /** Admin cere documente suplimentare (firmă, autorizații, DSVSA, certificări, fotografii…). */
    public function requestDocuments(Producer $producer, array $types, ?string $note, User $actor): void
    {
        DB::transaction(function () use ($producer, $types, $note, $actor) {
            foreach ($types as $type) {
                ProducerDocumentRequest::create([
                    'producer_id' => $producer->id, 'document_type' => $type, 'note' => $note, 'requested_by' => $actor->id,
                ]);
            }
            $this->transition($producer, ProducerStatus::Incomplete, $actor, 'Documente solicitate: '.implode(', ', $types).($note ? ' — '.$note : ''));
        });

        $lang = $producer->preferredLanguage()->value;
        $docs = implode(', ', array_map(fn ($t) => $this->docLabel($t, $lang), $types));

        $this->notifications->send('PRODUCER_DOCUMENTS_REQUESTED', $producer, [
            'producer_name' => $producer->brand_name, 'documents' => $docs, 'note' => $note ?: '—',
        ], [
            'related' => $producer,
            'action_url' => route('producer.documents'),
            'action_label_ro' => 'Încarcă documentele',
            'action_label_hu' => 'Dokumentumok feltöltése',
        ]);
    }

    protected function docLabel(string $type, string $lang): string
    {
        $map = [
            'company' => ['documente firmă', 'cégdokumentumok'],
            'authorization' => ['autorizații', 'engedélyek'],
            'dsvsa' => ['înregistrare / autorizare DSVSA', 'DSVSA nyilvántartás / engedély'],
            'certification' => ['certificări', 'tanúsítványok'],
            'photo' => ['fotografii din producție', 'fotók a termelésről'],
            'other' => ['alte documente', 'egyéb dokumentumok'],
        ];

        return $map[$type][$lang === 'hu' ? 1 : 0] ?? (DocumentType::tryFrom($type)?->label() ?? $type);
    }

    protected function transition(Producer $producer, ProducerStatus $to, User $actor, ?string $note): void
    {
        $from = $producer->status;
        $producer->forceFill([
            'status' => $to,
            'status_changed_at' => now(),
            'approved_at' => $to === ProducerStatus::Approved ? now() : ($to === ProducerStatus::Rejected ? null : $producer->approved_at),
            'approved_by' => $to === ProducerStatus::Approved ? $actor->id : $producer->approved_by,
            'rejection_reason' => $to === ProducerStatus::Rejected ? $note : null,
        ])->save();

        ProducerStatusHistory::create([
            'producer_id' => $producer->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'user_id' => $actor->id,
            'note' => $note,
        ]);
    }
}
