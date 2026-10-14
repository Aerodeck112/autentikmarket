<?php

namespace Database\Seeders;

use App\Models\TaskTemplate;
use Illuminate\Database\Seeder;

class TaskTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // [key, zile relative, rol, prioritate, titlu RO, titlu HU, fază]
        $rows = [
            ['EVENT_CITY_HALL', -30, 'organizer', 'high', 'Notificare / aviz Primărie', 'Polgármesteri hivatal értesítése / engedély', 'pre_event'],
            ['EVENT_DSVSA', -30, 'organizer', 'critical', 'Notificare DSV / DSVSA', 'DSV / DSVSA értesítés', 'pre_event'],
            ['EVENT_DSP', -30, 'organizer', 'high', 'Notificare DSP', 'DSP értesítés', 'pre_event'],
            ['EVENT_VENUE', -30, 'organizer', 'high', 'Confirmare locație', 'Helyszín visszaigazolása', 'pre_event'],
            ['EVENT_PERMITS', -30, 'organizer', 'high', 'Verificare autorizații', 'Engedélyek ellenőrzése', 'pre_event'],
            ['EVENT_OPEN_REGISTRATIONS', -30, 'organizer', 'normal', 'Deschidere înscrieri producători', 'Termelői jelentkezések megnyitása', 'pre_event'],
            ['EVENT_PRODUCERS_REVIEW', -14, 'organizer', 'high', 'Verificare cereri producători', 'Termelői jelentkezések ellenőrzése', 'pre_event'],
            ['EVENT_PROMOTION', -14, 'organizer', 'normal', 'Plan de promovare', 'Promóciós terv', 'pre_event'],
            ['EVENT_LOGISTICS', -14, 'organizer', 'normal', 'Logistică (transport, echipamente)', 'Logisztika (szállítás, felszerelés)', 'pre_event'],
            ['EVENT_FINAL_LIST', -7, 'organizer', 'high', 'Listă finală producători', 'Végleges termelői lista', 'pre_event'],
            ['EVENT_MAP_FINAL', -7, 'organizer', 'high', 'Hartă finală standuri', 'Végleges standtérkép', 'pre_event'],
            ['EVENT_ELECTRICITY', -7, 'organizer', 'normal', 'Electricitate — necesar și branșamente', 'Áram — igények és csatlakozások', 'pre_event'],
            ['EVENT_TABLES_TENTS', -7, 'organizer', 'normal', 'Mese și corturi', 'Asztalok és sátrak', 'pre_event'],
            ['EVENT_MISSING_DOCS', -7, 'organizer', 'high', 'Documente lipsă producători', 'Hiányzó termelői dokumentumok', 'pre_event'],
            ['EVENT_REMINDERS', -3, 'organizer', 'normal', 'Verificare remindere trimise', 'Emlékeztetők ellenőrzése', 'pre_event'],
            ['EVENT_STAFF', -3, 'organizer', 'high', 'Program staff', 'Személyzet beosztása', 'pre_event'],
            ['EVENT_SOCIAL', -3, 'organizer', 'normal', 'Postări social media', 'Közösségi média bejegyzések', 'pre_event'],
            ['EVENT_FINAL_CHECKLIST', -1, 'organizer', 'critical', 'Checklist final', 'Végső ellenőrzőlista', 'pre_event'],
            ['EVENT_QR', -1, 'staff', 'high', 'Pregătire QR / telefoane check-in', 'QR / beléptető telefonok előkészítése', 'pre_event'],
            ['EVENT_PRIZES', -1, 'organizer', 'normal', 'Premii fidelitate și tombolă', 'Hűség- és tombolaajándékok', 'pre_event'],
            ['EVENT_PRINT_LISTS', -1, 'organizer', 'normal', 'Liste tipărite (producători, standuri)', 'Nyomtatott listák (termelők, standok)', 'pre_event'],
            // după târg
            ['POST_THANKS', 1, 'organizer', 'normal', 'Verificare mulțumiri trimise', 'Köszönőüzenetek ellenőrzése', 'post_event'],
            ['POST_STATS', 2, 'organizer', 'normal', 'Statistici ediție', 'A vásár statisztikái', 'post_event'],
            ['POST_PHOTOS', 2, 'organizer', 'normal', 'Selecție și încărcare fotografii', 'Fotók kiválasztása és feltöltése', 'post_event'],
            ['POST_PARTICIPANTS', 2, 'organizer', 'normal', 'Centralizare participanți (prezenți / absenți)', 'Résztvevők összesítése (jelen / hiányzó)', 'post_event'],
            ['POST_RAFFLE', 3, 'organizer', 'normal', 'Tombolă — premii ridicate', 'Tombola — átadott nyeremények', 'post_event'],
            ['POST_PRIZES', 3, 'organizer', 'low', 'Inventar premii rămase', 'Megmaradt ajándékok leltára', 'post_event'],
            ['POST_REVIEWS', 5, 'organizer', 'normal', 'Moderare recenzii', 'Értékelések moderálása', 'post_event'],
            ['POST_REPORT', 5, 'organizer', 'normal', 'Raport ediție', 'Vásárjelentés', 'post_event'],
            ['POST_BLOG', 5, 'organizer', 'normal', 'Revizuire și publicare articol blog', 'Blogcikk átnézése és közzététele', 'post_event'],
            ['POST_NEXT_EDITION', 7, 'organizer', 'normal', 'Promovare ediția următoare', 'A következő vásár népszerűsítése', 'post_event'],
        ];

        foreach ($rows as $i => [$key, $days, $role, $priority, $ro, $hu, $phase]) {
            TaskTemplate::firstOrCreate(['key' => $key], [
                'title_ro' => $ro, 'title_hu' => $hu, 'relative_days' => $days, 'responsible_role' => $role,
                'priority' => $priority, 'phase' => $phase, 'active' => true, 'sort_order' => $i + 1,
            ]);
        }
    }
}
