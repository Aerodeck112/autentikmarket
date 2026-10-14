<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * Template-uri de notificare RO/HU. Placeholder-e: {{name}}, {{event_title}}, …
 * firstOrCreate: textele editate din CRM nu sunt suprascrise la update.
 */
class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $key => $t) {
            NotificationTemplate::firstOrCreate(
                ['key' => $key, 'channel' => $t['channel'] ?? 'mail'],
                [
                    'subject_ro' => $t['subject_ro'],
                    'subject_hu' => $t['subject_hu'],
                    'body_ro' => $t['body_ro'],
                    'body_hu' => $t['body_hu'],
                    'description' => $t['description'],
                    'active' => true,
                ]
            );
        }
    }

    public function templates(): array
    {
        return [
            'ACCOUNT_WELCOME' => [
                'description' => 'Bun venit — cont nou vizitator',
                'subject_ro' => 'Bine ai venit în comunitatea Autentik Market!',
                'subject_hu' => 'Üdvözlünk az Autentik Market közösségében!',
                'body_ro' => "Bună, {{name}}!\n\nÎți mulțumim că ți-ai creat cont. Cardul tău Autentik (cod {{member_code}}) este gata: arată codul QR la intrarea în târg și fiecare vizită se adaugă automat.\n\nLa 5 și 10 vizite te așteaptă cadouri de la producătorii noștri.\n\nNe vedem la târg!",
                'body_hu' => "Szia, {{name}}!\n\nKöszönjük, hogy regisztráltál. Autentik kártyád ({{member_code}}) elkészült: mutasd fel a QR-kódot a vásár bejáratánál, és minden látogatásod automatikusan rögzítésre kerül.\n\n5 és 10 látogatás után termelőink ajándékai várnak.\n\nTalálkozunk a vásáron!",
            ],
            'EMAIL_VERIFY' => [
                'description' => 'Confirmare adresă de e-mail',
                'subject_ro' => 'Confirmă adresa de e-mail',
                'subject_hu' => 'Erősítsd meg az e-mail-címed',
                'body_ro' => "Bună, {{name}}!\n\nTe rugăm să confirmi adresa de e-mail apăsând butonul de mai jos. Linkul este valabil 60 de minute.",
                'body_hu' => "Szia, {{name}}!\n\nKérjük, erősítsd meg e-mail-címedet az alábbi gombra kattintva. A link 60 percig érvényes.",
            ],
            'PASSWORD_RESET' => [
                'description' => 'Resetare parolă',
                'subject_ro' => 'Resetarea parolei',
                'subject_hu' => 'Jelszó visszaállítása',
                'body_ro' => "Bună, {{name}}!\n\nAm primit o cerere de resetare a parolei pentru contul tău. Linkul este valabil 60 de minute.\n\nDacă nu tu ai făcut cererea, poți ignora acest mesaj.",
                'body_hu' => "Szia, {{name}}!\n\nJelszó-visszaállítási kérést kaptunk a fiókodhoz. A link 60 percig érvényes.\n\nHa nem te kérted, nyugodtan figyelmen kívül hagyhatod ezt az üzenetet.",
            ],
            'NEWSLETTER_CONFIRM' => [
                'description' => 'Confirmare abonare newsletter (double opt-in)',
                'subject_ro' => 'Confirmă abonarea la newsletter',
                'subject_hu' => 'Erősítsd meg a hírlevél-feliratkozást',
                'body_ro' => "Bună!\n\nConfirmă abonarea la newsletterul Autentik Market pentru a afla primul despre târgurile următoare și producătorii noi.",
                'body_hu' => "Szia!\n\nErősítsd meg feliratkozásodat az Autentik Market hírlevelére, hogy elsőként értesülj a következő vásárokról és az új termelőkről.",
            ],
            'PRODUCER_REGISTERED' => [
                'description' => 'Producător — cont creat',
                'subject_ro' => 'Am primit înregistrarea ta — {{producer_name}}',
                'subject_hu' => 'Megkaptuk regisztrációdat — {{producer_name}}',
                'body_ro' => "Bună, {{name}}!\n\nÎți mulțumim pentru interesul de a participa la Autentik Market. Completează profilul și încarcă documentele, apoi trimite-le spre verificare.\n\nLa Autentik Market participă doar producători și artizani reali — verificarea ne ajută să păstrăm această promisiune față de vizitatori.",
                'body_hu' => "Szia, {{name}}!\n\nKöszönjük érdeklődésedet az Autentik Market iránt. Töltsd ki a profilodat, töltsd fel a dokumentumokat, majd küldd el ellenőrzésre.\n\nAz Autentik Marketen csak valódi termelők és kézművesek vesznek részt — az ellenőrzés segít megtartani ezt az ígéretet a látogatók felé.",
            ],
            'PRODUCER_DOCUMENTS_REQUESTED' => [
                'description' => 'Producător — documente solicitate',
                'subject_ro' => 'Avem nevoie de documente suplimentare',
                'subject_hu' => 'További dokumentumokra van szükségünk',
                'body_ro' => "Bună, {{name}}!\n\nPentru verificarea profilului {{producer_name}} avem nevoie de: {{documents}}.\n\nNotă: {{note}}\n\nPoți încărca documentele din contul tău de producător.",
                'body_hu' => "Szia, {{name}}!\n\nA(z) {{producer_name}} profil ellenőrzéséhez a következőkre van szükségünk: {{documents}}.\n\nMegjegyzés: {{note}}\n\nA dokumentumokat termelői fiókodból töltheted fel.",
            ],
            'PRODUCER_APPROVED' => [
                'description' => 'Producător — aprobat',
                'subject_ro' => 'Felicitări! {{producer_name}} a fost aprobat',
                'subject_hu' => 'Gratulálunk! A(z) {{producer_name}} jóváhagyásra került',
                'body_ro' => "Bună, {{name}}!\n\nProfilul tău a fost verificat și aprobat. Acum poți alege standul la următoarele târguri Autentik Market direct din contul tău.\n\nBine ai venit în comunitate!",
                'body_hu' => "Szia, {{name}}!\n\nProfilodat ellenőriztük és jóváhagytuk. Mostantól közvetlenül a fiókodból választhatsz standot a következő Autentik Market vásárokra.\n\nÜdvözlünk a közösségben!",
            ],
            'PRODUCER_REJECTED' => [
                'description' => 'Producător — respins',
                'subject_ro' => 'Rezultatul verificării — {{producer_name}}',
                'subject_hu' => 'Az ellenőrzés eredménye — {{producer_name}}',
                'body_ro' => "Bună, {{name}}!\n\nÎți mulțumim pentru interes. Din păcate, în acest moment nu putem aproba profilul {{producer_name}}.\n\nMotiv: {{reason}}\n\nPentru întrebări, ne poți răspunde la acest e-mail.",
                'body_hu' => "Szia, {{name}}!\n\nKöszönjük érdeklődésedet. Sajnos jelenleg nem tudjuk jóváhagyni a(z) {{producer_name}} profilt.\n\nIndoklás: {{reason}}\n\nKérdés esetén válaszolj erre az e-mailre.",
            ],
            'PRODUCER_CONTRACT_READY' => [
                'description' => 'Producător — contract generat',
                'subject_ro' => 'Contractul tău Autentik Market ({{contract_number}})',
                'subject_hu' => 'Autentik Market szerződésed ({{contract_number}})',
                'body_ro' => "Bună, {{name}}!\n\nContractul nr. {{contract_number}} este disponibil în contul tău, la secțiunea Documente.",
                'body_hu' => "Szia, {{name}}!\n\nA(z) {{contract_number}} számú szerződés elérhető fiókod Dokumentumok részében.",
            ],
            'EVENT_REGISTRATION_RECEIVED' => [
                'description' => 'Producător — cerere stand trimisă',
                'subject_ro' => 'Cererea ta pentru standul {{stand_code}} a fost trimisă',
                'subject_hu' => 'A(z) {{stand_code}} standra vonatkozó kérésed elküldtük',
                'body_ro' => "Bună, {{name}}!\n\nAm primit cererea de participare la {{event_title}} ({{event_date}}), standul {{stand_code}} ({{stand_size}}).\n\nTe anunțăm imediat ce echipa o aprobă.",
                'body_hu' => "Szia, {{name}}!\n\nMegkaptuk részvételi kérésedet: {{event_title}} ({{event_date}}), {{stand_code}} stand ({{stand_size}}).\n\nAmint a csapat jóváhagyja, értesítünk.",
            ],
            'RESERVATION_APPROVED' => [
                'description' => 'Producător — stand aprobat',
                'subject_ro' => 'Standul {{stand_code}} este al tău — {{event_title}}',
                'subject_hu' => 'A(z) {{stand_code}} stand a tiéd — {{event_title}}',
                'body_ro' => "Bună, {{name}}!\n\nParticiparea ta la {{event_title}} ({{event_date}}) a fost aprobată. Standul tău: {{stand_code}} ({{stand_size}}).\n\nAnexa la contract este disponibilă în contul tău.",
                'body_hu' => "Szia, {{name}}!\n\nRészvételed a(z) {{event_title}} vásáron ({{event_date}}) jóváhagyásra került. Standod: {{stand_code}} ({{stand_size}}).\n\nA szerződésmelléklet elérhető a fiókodban.",
            ],
            'RESERVATION_REJECTED' => [
                'description' => 'Producător — stand respins',
                'subject_ro' => 'Cererea pentru standul {{stand_code}} nu a putut fi aprobată',
                'subject_hu' => 'A(z) {{stand_code}} standra vonatkozó kérést nem tudtuk jóváhagyni',
                'body_ro' => "Bună, {{name}}!\n\nDin păcate, cererea ta pentru {{event_title}} ({{event_date}}), standul {{stand_code}}, nu a putut fi aprobată.\n\nMotiv: {{reason}}\n\nPoți alege un alt stand, dacă mai sunt locuri disponibile.",
                'body_hu' => "Szia, {{name}}!\n\nSajnos a(z) {{event_title}} ({{event_date}}) vásárra, a(z) {{stand_code}} standra vonatkozó kérésedet nem tudtuk jóváhagyni.\n\nIndoklás: {{reason}}\n\nHa van még szabad hely, választhatsz másik standot.",
            ],
            'ANNEX_READY' => [
                'description' => 'Producător — anexă generată',
                'subject_ro' => 'Anexa {{annex_number}} — {{event_title}}',
                'subject_hu' => 'Melléklet {{annex_number}} — {{event_title}}',
                'body_ro' => "Bună, {{name}}!\n\nAnexa nr. {{annex_number}} pentru {{event_title}} este disponibilă în contul tău, la secțiunea Documente.",
                'body_hu' => "Szia, {{name}}!\n\nA(z) {{event_title}} vásárra vonatkozó {{annex_number}} számú melléklet elérhető fiókod Dokumentumok részében.",
            ],
            'EVENT_REMINDER_PRODUCER' => [
                'description' => 'Producător — reminder cu 3 zile înainte',
                'subject_ro' => 'Ne vedem în curând: {{event_title}}',
                'subject_hu' => 'Hamarosan találkozunk: {{event_title}}',
                'body_ro' => "Bună, {{name}}!\n\nÎți reamintim că participi la {{event_title}}, {{event_date}}, {{event_time}}.\nAdresa: {{event_address}}\nStandul tău: {{stand_code}}\n\nTe rugăm să ajungi cu cel puțin o oră înainte de deschidere pentru amenajare.",
                'body_hu' => "Szia, {{name}}!\n\nEmlékeztetünk, hogy részt veszel a(z) {{event_title}} vásáron: {{event_date}}, {{event_time}}.\nCím: {{event_address}}\nStandod: {{stand_code}}\n\nKérjük, érkezz legalább egy órával a nyitás előtt a berendezéshez.",
            ],
            'EVENT_REMINDER_VISITOR' => [
                'description' => 'Vizitator — reminder târg (doar cu acord)',
                'subject_ro' => 'Sâmbătă e târg: {{event_title}}',
                'subject_hu' => 'Szombaton vásár: {{event_title}}',
                'body_ro' => "Bună, {{name}}!\n\nTe așteptăm la {{event_title}}, {{event_date}}, {{event_time}}, la {{event_address}}.\n\nNu uita cardul Autentik — fiecare vizită te apropie de următorul cadou.",
                'body_hu' => "Szia, {{name}}!\n\nVárunk a(z) {{event_title}} vásáron: {{event_date}}, {{event_time}}, helyszín: {{event_address}}.\n\nNe felejtsd el Autentik kártyádat — minden látogatás közelebb visz a következő ajándékhoz.",
            ],
            'EVENT_THANK_YOU_VISITOR' => [
                'description' => 'Vizitator — mulțumire după târg',
                'subject_ro' => 'Îți mulțumim că ai fost alături de noi!',
                'subject_hu' => 'Köszönjük, hogy velünk voltál!',
                'body_ro' => "Bună, {{name}}!\n\nÎți mulțumim că ai participat la {{event_title}} din {{event_date}}. Ai acum {{visits}} vizite pe cardul Autentik.\n\nFiecare cumpărătură directă de la producător contează. Ne vedem la ediția următoare!",
                'body_hu' => "Szia, {{name}}!\n\nKöszönjük, hogy eljöttél a(z) {{event_title}} vásárra ({{event_date}}). Autentik kártyádon most {{visits}} látogatás szerepel.\n\nMinden közvetlenül a termelőtől vásárolt termék számít. Találkozunk a következő alkalommal!",
            ],
            'EVENT_THANK_YOU_PRODUCER' => [
                'description' => 'Producător — mulțumire după târg',
                'subject_ro' => 'Mulțumim pentru participare — {{event_title}}',
                'subject_hu' => 'Köszönjük a részvételt — {{event_title}}',
                'body_ro' => "Bună, {{name}}!\n\nÎți mulțumim că ai fost alături de noi la {{event_title}} ({{event_date}}). Tu ești motivul pentru care oamenii vin la Autentik Market.\n\nÎn curând deschidem înscrierile pentru ediția următoare.",
                'body_hu' => "Szia, {{name}}!\n\nKöszönjük, hogy velünk voltál a(z) {{event_title}} vásáron ({{event_date}}). Miattad jönnek az emberek az Autentik Marketre.\n\nHamarosan megnyitjuk a jelentkezést a következő vásárra.",
            ],
            'REVIEW_REQUEST' => [
                'description' => 'Vizitator — cerere recenzie (după interacțiune cu producătorul)',
                'subject_ro' => 'Cum ți s-a părut {{producer_name}}?',
                'subject_hu' => 'Milyen volt a(z) {{producer_name}}?',
                'body_ro' => "Bună, {{name}}!\n\nLa {{event_title}} ai cumpărat de la {{producer_name}}. Ne ajuți cu o scurtă recenzie? Durează un minut și îi ajută pe alți vizitatori.",
                'body_hu' => "Szia, {{name}}!\n\nA(z) {{event_title}} vásáron vásároltál a(z) {{producer_name}} standjánál. Segítenél egy rövid értékeléssel? Egy perc az egész, és más látogatóknak is segít.",
            ],
            'LOYALTY_REWARD_EARNED' => [
                'description' => 'Vizitator — recompensă de fidelitate',
                'subject_ro' => 'Ai câștigat: {{reward_title}}',
                'subject_hu' => 'Megszerezted: {{reward_title}}',
                'body_ro' => "Felicitări, {{name}}!\n\nAi ajuns la {{visits}} vizite și ai primit: {{reward_title}}.\n{{reward_description}}\n\nArată cardul Autentik la standul de informații pentru a ridica recompensa.",
                'body_hu' => "Gratulálunk, {{name}}!\n\nElérted a(z) {{visits}} látogatást, és megkaptad: {{reward_title}}.\n{{reward_description}}\n\nMutasd fel Autentik kártyádat az információs standnál az ajándék átvételéhez.",
            ],
            'RAFFLE_WINNER' => [
                'description' => 'Vizitator — câștigător tombolă',
                'subject_ro' => 'Ai câștigat la tombola {{raffle_name}}!',
                'subject_hu' => 'Nyertél a(z) {{raffle_name}} tombolán!',
                'body_ro' => "Felicitări, {{name}}!\n\nAi fost extras câștigător la {{raffle_name}}. Premiul tău: {{prize_name}}.\n\nRidică premiul de la standul de informații, pe baza cardului Autentik.",
                'body_hu' => "Gratulálunk, {{name}}!\n\nNyertesként húztak ki a(z) {{raffle_name}} tombolán. Nyereményed: {{prize_name}}.\n\nA nyereményt Autentik kártyád felmutatásával veheted át az információs standnál.",
            ],
            'TASK_REMINDER' => [
                'description' => 'Organizator — reminder task',
                'subject_ro' => 'Task {{overdue}}: {{task_title}}',
                'subject_hu' => 'Feladat {{overdue}}: {{task_title}}',
                'body_ro' => "Bună, {{name}}!\n\nTask: {{task_title}}\nTermen: {{due_date}}\nEveniment: {{event_title}}",
                'body_hu' => "Szia, {{name}}!\n\nFeladat: {{task_title}}\nHatáridő: {{due_date}}\nEsemény: {{event_title}}",
            ],
            'ADMIN_NEW_PRODUCER' => [
                'description' => 'Intern — producător trimis spre verificare',
                'subject_ro' => 'Producător nou de verificat: {{producer_name}}',
                'subject_hu' => 'Új ellenőrizendő termelő: {{producer_name}}',
                'body_ro' => 'Producătorul {{producer_name}} ({{city}}) a trimis profilul și documentele spre verificare.',
                'body_hu' => 'A(z) {{producer_name}} ({{city}}) termelő ellenőrzésre küldte profilját és dokumentumait.',
            ],
            'TEST_EMAIL' => [
                'description' => 'Test configurare SMTP',
                'subject_ro' => 'Test e-mail Autentik Market',
                'subject_hu' => 'Autentik Market teszt e-mail',
                'body_ro' => "Bună, {{name}}!\n\nDacă citești acest mesaj, configurarea e-mail funcționează corect.",
                'body_hu' => "Szia, {{name}}!\n\nHa ezt az üzenetet olvasod, az e-mail-beállítás megfelelően működik.",
            ],
        ];
    }
}
