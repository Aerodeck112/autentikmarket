<?php

namespace App\Services\Documents;

use App\Exceptions\BusinessRuleException;
use App\Models\Annex;
use App\Models\Contract;
use App\Models\DocumentTemplate;
use App\Models\Producer;
use App\Models\StandReservation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\NotificationService;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Contracte și anexe: template HTML editabil în CRM + placeholder-e → PDF (dompdf, PHP pur).
 * PDF-urile se stochează PRIVAT (storage/app/private) și se servesc doar cu verificare de permisiuni.
 */
class DocumentService
{
    public const CONTRACT_PLACEHOLDERS = [
        'contract_number', 'date', 'producer_name', 'company_name', 'cui', 'trade_register', 'representative',
        'address', 'city', 'county', 'phone', 'email', 'iban', 'bank', 'category',
        'org_name', 'org_legal_name', 'org_cui', 'org_trade_register', 'org_address', 'org_representative', 'org_iban',
    ];

    public const ANNEX_PLACEHOLDERS = [
        'annex_number', 'contract_number', 'date', 'producer_name', 'company_name', 'cui', 'representative',
        'event_title', 'event_date', 'event_time', 'event_location', 'event_address',
        'stand_code', 'stand_size', 'stand_price', 'electricity', 'services', 'observations',
        'org_name', 'org_legal_name', 'org_cui', 'org_representative',
    ];

    public function __construct(protected NotificationService $notifications) {}

    public function activeTemplate(string $type, string $language): ?DocumentTemplate
    {
        return DocumentTemplate::where('type', $type)->where('is_active', true)
            ->orderByRaw('case when language = ? then 0 else 1 end', [$language])
            ->latest('version')->first();
    }

    public function generateContract(Producer $producer, User $admin): Contract
    {
        if (! $producer->isApproved()) {
            throw new BusinessRuleException(__('Contractul se poate genera doar pentru producători aprobați.'), 'producer_not_approved');
        }
        $lang = $producer->preferredLanguage()->value;
        $template = $this->activeTemplate('contract', $lang)
            ?? throw new BusinessRuleException(__('Nu există un template de contract activ.'), 'no_template');

        $contract = $this->withUniqueNumber('C', function (string $number) use ($producer, $template, $lang, $admin) {
            $data = $this->producerData($producer, $lang) + $this->orgData() + [
                'contract_number' => $number,
                'date' => format_date_local(now(), false, $lang),
            ];
            $uuid = (string) Str::uuid();
            $path = "contracts/{$producer->id}/{$uuid}.pdf";
            $this->renderPdf($template, $data, $path, __('Contract').' '.$number, $lang);

            return Contract::create([
                'uuid' => $uuid,
                'producer_id' => $producer->id,
                'document_template_id' => $template->id,
                'number' => $number,
                'language' => $template->language,
                'path' => $path,
                'status' => 'generated',
                'data_snapshot' => $data,
                'generated_by' => $admin->id,
                'generated_at' => now(),
            ]);
        });

        AuditLogger::log('contract.generated', $contract, [], ['number' => $contract->number, 'producer_id' => $producer->id], $admin->id);
        $this->notifications->send('PRODUCER_CONTRACT_READY', $producer, ['contract_number' => $contract->number], [
            'related' => $contract,
            'dedupe_key' => 'contract-ready:'.$contract->id,
            'action_url' => route('producer.documents'),
            'action_label_ro' => 'Vezi contractul',
            'action_label_hu' => 'Szerződés megtekintése',
        ]);

        return $contract;
    }

    public function generateAnnex(StandReservation $reservation, User $admin): Annex
    {
        $reservation->loadMissing(['producer', 'event.venue', 'slot', 'registration']);
        $producer = $reservation->producer;
        $event = $reservation->event;
        $lang = $producer->preferredLanguage()->value;
        $template = $this->activeTemplate('annex', $lang)
            ?? throw new BusinessRuleException(__('Nu există un template de anexă activ.'), 'no_template');

        $contract = $producer->contracts()->whereIn('status', ['generated', 'sent', 'signed'])->latest('id')->first();
        $reg = $reservation->registration;
        $yes = $lang === 'hu' ? 'IGEN' : 'DA';
        $no = $lang === 'hu' ? 'NEM' : 'NU';
        $services = array_filter([
            $reg?->needs_table ? ($lang === 'hu' ? 'asztal' : 'masă') : null,
            $reg?->needs_tent ? ($lang === 'hu' ? 'sátor' : 'cort') : null,
            ($reg?->needs_electricity || $reservation->slot->has_electricity) ? ($lang === 'hu' ? 'áram' : 'electricitate') : null,
        ]);

        $annex = $this->withUniqueNumber('A', function (string $number) use ($reservation, $producer, $event, $lang, $template, $contract, $reg, $yes, $no, $services, $admin) {
            $data = $this->producerData($producer, $lang) + $this->orgData() + [
                'annex_number' => $number,
                'contract_number' => $contract?->number ?? '—',
                'date' => format_date_local(now(), false, $lang),
                'event_title' => $event->t('title', $lang),
                'event_date' => format_date_local($event->event_date, true, $lang),
                'event_time' => $event->timeRange(),
                'event_location' => $event->venue_name ?: ($event->venue?->name ?? $event->city),
                'event_address' => trim(($event->address ?? '').', '.$event->city, ', '),
                'stand_code' => $reservation->slot->code,
                'stand_size' => $reservation->slot->dimensions(),
                'stand_price' => $reservation->price !== null ? number_format((float) $reservation->price, 2, ',', '.').' lei' : '[tarif de stabilit]',
                'electricity' => $reservation->slot->has_electricity ? $yes : $no,
                'services' => $services ? implode(', ', $services) : '—',
                'observations' => trim(($reg?->notes ?? '').' '.($reservation->admin_notes ?? '')) ?: '—',
            ];
            $uuid = (string) Str::uuid();
            $path = "annexes/{$producer->id}/{$uuid}.pdf";
            $this->renderPdf($template, $data, $path, __('Anexă').' '.$number, $lang);

            return Annex::create([
                'uuid' => $uuid,
                'contract_id' => $contract?->id,
                'producer_id' => $producer->id,
                'event_id' => $event->id,
                'event_registration_id' => $reg?->id,
                'stand_reservation_id' => $reservation->id,
                'document_template_id' => $template->id,
                'number' => $number,
                'language' => $template->language,
                'path' => $path,
                'data_snapshot' => $data,
                'generated_by' => $admin->id,
                'generated_at' => now(),
            ]);
        });

        AuditLogger::log('annex.generated', $annex, [], ['number' => $annex->number, 'event_id' => $event->id, 'producer_id' => $producer->id], $admin->id);
        $this->notifications->send('ANNEX_READY', $producer, [
            'annex_number' => $annex->number,
            'event_title' => $event->t('title', $lang),
        ], ['related' => $annex, 'dedupe_key' => 'annex-ready:'.$annex->id]);

        return $annex;
    }

    /** Înlocuire sigură: valorile sunt escapate HTML, template-ul nu poate executa cod. */
    public function fill(string $html, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($data) {
            return array_key_exists($m[1], $data) ? e((string) $data[$m[1]]) : $m[0];
        }, $html) ?? $html;
    }

    public function previewHtml(DocumentTemplate $template): string
    {
        $sample = array_fill_keys(array_merge(self::CONTRACT_PLACEHOLDERS, self::ANNEX_PLACEHOLDERS), '…');
        $sample = array_merge($sample, $this->orgData(), ['date' => format_date_local(now(), false, $template->language)]);

        return $this->fill($template->body_html, $sample);
    }

    protected function renderPdf(DocumentTemplate $template, array $data, string $path, string $title, string $lang): void
    {
        $body = $this->fill($template->body_html, $data);
        $html = view('pdf.document', ['title' => $title, 'body' => $body, 'lang' => $lang])->render();
        $pdf = Pdf::loadHTML($html)->setPaper('a4');
        Storage::disk('private')->put($path, $pdf->output());
    }

    protected function withUniqueNumber(string $kind, callable $create)
    {
        $prefix = Settings::get('contracts.number_prefix', 'AM');
        $year = now()->year;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $model = $kind === 'C' ? Contract::class : Annex::class;
            $seq = $model::where('number', 'like', "{$prefix}-{$kind}-{$year}-%")->count() + 1 + $attempt;
            $number = sprintf('%s-%s-%d-%04d', $prefix, $kind, $year, $seq);
            try {
                return $create($number);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }
        throw new BusinessRuleException(__('Nu s-a putut genera un număr unic de document.'), 'number_conflict');
    }

    protected function producerData(Producer $p, string $lang): array
    {
        return [
            'producer_name' => $p->brand_name,
            'company_name' => $p->legal_name ?: '[denumire juridică lipsă]',
            'cui' => $p->cui ?: '[CUI lipsă]',
            'trade_register' => $p->trade_register_no ?: '—',
            'representative' => $p->representative_name ?: '[reprezentant lipsă]',
            'address' => $p->address ?: '—',
            'city' => $p->city ?: '—',
            'county' => $p->county ?: '—',
            'phone' => $p->phone ?: '—',
            'email' => $p->email ?: '—',
            'iban' => $p->iban ?: '—',
            'bank' => $p->bank_name ?: '—',
            'category' => $p->category?->t('name', $lang) ?? '—',
        ];
    }

    protected function orgData(): array
    {
        return [
            'org_name' => Settings::get('org.name'),
            'org_legal_name' => Settings::get('org.legal_name'),
            'org_cui' => Settings::get('org.cui'),
            'org_trade_register' => Settings::get('org.trade_register'),
            'org_address' => Settings::get('org.address'),
            'org_representative' => Settings::get('org.representative'),
            'org_iban' => Settings::get('org.iban'),
        ];
    }
}
