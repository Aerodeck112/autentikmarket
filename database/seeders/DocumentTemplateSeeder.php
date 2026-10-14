<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

/**
 * Template-uri de contract / anexă: SCHELET cu placeholder-e.
 * Textul juridic NU este inventat — secțiunile marcate [DE COMPLETAT] trebuie redactate de jurist.
 */
class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['contract', 'ro', 'Contract de participare — RO', $this->contractRo()],
            ['contract', 'hu', 'Részvételi szerződés — HU', $this->contractHu()],
            ['annex', 'ro', 'Anexă eveniment — RO', $this->annexRo()],
            ['annex', 'hu', 'Rendezvény melléklet — HU', $this->annexHu()],
        ];
        foreach ($templates as [$type, $lang, $name, $body]) {
            DocumentTemplate::firstOrCreate(['type' => $type, 'language' => $lang], ['name' => $name, 'body_html' => $body, 'is_active' => true]);
        }
    }

    protected function contractRo(): string
    {
        return <<<'HTML'
<h1>CONTRACT DE PARTICIPARE nr. {{contract_number}}</h1>
<p class="muted">încheiat astăzi, {{date}}</p>

<h2>Părțile</h2>
<p><strong>{{org_legal_name}}</strong>, CUI {{org_cui}}, Nr. Reg. Com. {{org_trade_register}}, cu sediul în {{org_address}}, reprezentată de {{org_representative}}, denumită în continuare <em>Organizatorul</em>,</p>
<p>și</p>
<p><strong>{{company_name}}</strong> („{{producer_name}}”), CUI {{cui}}, Nr. Reg. Com. {{trade_register}}, cu sediul în {{address}}, {{city}}, jud. {{county}}, reprezentată de {{representative}}, telefon {{phone}}, e-mail {{email}}, IBAN {{iban}} ({{bank}}), denumită în continuare <em>Participantul</em>.</p>

<h2>1. Obiectul contractului</h2>
<p class="placeholder">[DE COMPLETAT DE JURIST — obiectul contractului: participarea Participantului la târgurile Autentik Market, în calitate de producător/artizan, categoria {{category}}.]</p>

<h2>2. Durata</h2>
<p class="placeholder">[DE COMPLETAT — durata contractului și condițiile de prelungire.]</p>

<h2>3. Obligațiile Organizatorului</h2>
<p class="placeholder">[DE COMPLETAT]</p>

<h2>4. Obligațiile Participantului</h2>
<p class="placeholder">[DE COMPLETAT — inclusiv: comercializarea exclusiv a produselor proprii (fără revânzare), respectarea normelor sanitar-veterinare și fiscale în vigoare, deținerea autorizațiilor necesare.]</p>

<h2>5. Tarife și plată</h2>
<p class="placeholder">[DE COMPLETAT — tarifele per eveniment se stabilesc prin anexa fiecărei participări.]</p>

<h2>6. Încetarea contractului</h2>
<p class="placeholder">[DE COMPLETAT]</p>

<h2>7. Protecția datelor cu caracter personal</h2>
<p class="placeholder">[DE COMPLETAT — conform Regulamentului (UE) 2016/679.]</p>

<h2>8. Dispoziții finale</h2>
<p class="placeholder">[DE COMPLETAT]</p>

<table class="signatures">
<tr><td><strong>Organizator</strong><br>{{org_legal_name}}<br>{{org_representative}}<br><br>Semnătura: ____________</td>
<td><strong>Participant</strong><br>{{company_name}}<br>{{representative}}<br><br>Semnătura: ____________</td></tr>
</table>
HTML;
    }

    protected function contractHu(): string
    {
        return <<<'HTML'
<h1>RÉSZVÉTELI SZERZŐDÉS {{contract_number}}</h1>
<p class="muted">kelt: {{date}}</p>

<h2>Felek</h2>
<p><strong>{{org_legal_name}}</strong>, adószám {{org_cui}}, cégjegyzékszám {{org_trade_register}}, székhely: {{org_address}}, képviseli: {{org_representative}}, a továbbiakban <em>Szervező</em>,</p>
<p>valamint</p>
<p><strong>{{company_name}}</strong> („{{producer_name}}”), adószám {{cui}}, cégjegyzékszám {{trade_register}}, székhely: {{address}}, {{city}}, {{county}} megye, képviseli: {{representative}}, telefon {{phone}}, e-mail {{email}}, IBAN {{iban}} ({{bank}}), a továbbiakban <em>Résztvevő</em>.</p>

<h2>1. A szerződés tárgya</h2>
<p class="placeholder">[JOGÁSZ ÁLTAL KITÖLTENDŐ — a Résztvevő részvétele az Autentik Market vásárokon termelőként/kézművesként, kategória: {{category}}.]</p>

<h2>2. Időtartam</h2>
<p class="placeholder">[KITÖLTENDŐ]</p>

<h2>3. A Szervező kötelezettségei</h2>
<p class="placeholder">[KITÖLTENDŐ]</p>

<h2>4. A Résztvevő kötelezettségei</h2>
<p class="placeholder">[KITÖLTENDŐ — többek között: kizárólag saját termékek értékesítése (viszonteladás nélkül), a hatályos állategészségügyi, élelmiszer-biztonsági és adózási előírások betartása, a szükséges engedélyek megléte.]</p>

<h2>5. Díjak és fizetés</h2>
<p class="placeholder">[KITÖLTENDŐ — az eseményenkénti díjakat az adott részvétel melléklete rögzíti.]</p>

<h2>6. A szerződés megszűnése</h2>
<p class="placeholder">[KITÖLTENDŐ]</p>

<h2>7. Személyes adatok védelme</h2>
<p class="placeholder">[KITÖLTENDŐ — az (EU) 2016/679 rendelet szerint.]</p>

<h2>8. Záró rendelkezések</h2>
<p class="placeholder">[KITÖLTENDŐ]</p>

<table class="signatures">
<tr><td><strong>Szervező</strong><br>{{org_legal_name}}<br>{{org_representative}}<br><br>Aláírás: ____________</td>
<td><strong>Résztvevő</strong><br>{{company_name}}<br>{{representative}}<br><br>Aláírás: ____________</td></tr>
</table>
HTML;
    }

    protected function annexRo(): string
    {
        return <<<'HTML'
<h1>ANEXA nr. {{annex_number}}</h1>
<p class="muted">la Contractul de participare nr. {{contract_number}} · {{date}}</p>

<table class="data">
<tr><th>Participant</th><td>{{producer_name}} — {{company_name}} (CUI {{cui}})</td></tr>
<tr><th>Reprezentant</th><td>{{representative}}</td></tr>
<tr><th>Eveniment</th><td>{{event_title}}</td></tr>
<tr><th>Data</th><td>{{event_date}}, {{event_time}}</td></tr>
<tr><th>Locația</th><td>{{event_location}}, {{event_address}}</td></tr>
<tr><th>Stand</th><td>{{stand_code}}</td></tr>
<tr><th>Dimensiune</th><td>{{stand_size}}</td></tr>
<tr><th>Tarif</th><td>{{stand_price}}</td></tr>
<tr><th>Electricitate</th><td>{{electricity}}</td></tr>
<tr><th>Servicii</th><td>{{services}}</td></tr>
<tr><th>Observații</th><td>{{observations}}</td></tr>
</table>

<p class="placeholder">[DE COMPLETAT — condiții specifice ale participării: program de amenajare, reguli de acces, modalitate de plată.]</p>

<table class="signatures">
<tr><td><strong>Organizator</strong><br>{{org_legal_name}}<br><br>Semnătura: ____________</td>
<td><strong>Participant</strong><br>{{company_name}}<br><br>Semnătura: ____________</td></tr>
</table>
HTML;
    }

    protected function annexHu(): string
    {
        return <<<'HTML'
<h1>MELLÉKLET {{annex_number}}</h1>
<p class="muted">a(z) {{contract_number}} számú részvételi szerződéshez · {{date}}</p>

<table class="data">
<tr><th>Résztvevő</th><td>{{producer_name}} — {{company_name}} (adószám {{cui}})</td></tr>
<tr><th>Képviselő</th><td>{{representative}}</td></tr>
<tr><th>Esemény</th><td>{{event_title}}</td></tr>
<tr><th>Időpont</th><td>{{event_date}}, {{event_time}}</td></tr>
<tr><th>Helyszín</th><td>{{event_location}}, {{event_address}}</td></tr>
<tr><th>Stand</th><td>{{stand_code}}</td></tr>
<tr><th>Méret</th><td>{{stand_size}}</td></tr>
<tr><th>Díj</th><td>{{stand_price}}</td></tr>
<tr><th>Áram</th><td>{{electricity}}</td></tr>
<tr><th>Szolgáltatások</th><td>{{services}}</td></tr>
<tr><th>Megjegyzések</th><td>{{observations}}</td></tr>
</table>

<p class="placeholder">[KITÖLTENDŐ — a részvétel egyedi feltételei: berendezési idő, belépési szabályok, fizetési mód.]</p>

<table class="signatures">
<tr><td><strong>Szervező</strong><br>{{org_legal_name}}<br><br>Aláírás: ____________</td>
<td><strong>Résztvevő</strong><br>{{company_name}}<br><br>Aláírás: ____________</td></tr>
</table>
HTML;
    }
}
