<?php

namespace App\Services\Blog;

use App\Enums\BlogStatus;
use App\Models\BlogPost;
use App\Models\Event;
use App\Services\Stats\StatisticsService;

class TemplateBlogDraftGenerator implements BlogDraftGenerator
{
    public function __construct(protected StatisticsService $stats) {}

    public function generate(Event $event): BlogPost
    {
        $s = $this->stats->eventStats($event);
        $cityHu = $event->venue?->city_hu ?: $event->city;
        $dateRo = format_date_local($event->event_date, false, 'ro');
        $dateHu = format_date_local($event->event_date, false, 'hu');

        $categoriesRo = implode(', ', $s['categories_ro']);
        $categoriesHu = implode(', ', $s['categories_hu']);

        $bodyRo = <<<TXT
        Sâmbătă, {$dateRo}, curtea s-a umplut din nou de oameni, mirosuri și povești. Autentik Market {$event->city} a adus împreună {$s['producers_present']} producători și artizani locali și {$s['visitors_total']} vizitatori cu cardul Autentik.

        Pe tarabe am regăsit: {$categoriesRo}.

        Mulțumim fiecărui producător care a venit cu ce a crescut, a gătit sau a lucrat cu mâna lui și fiecărui vizitator care a ales să cumpere direct de la sursă.

        [Completați aici: momente speciale, producători noi, câștigătorii tombolei, citate de la participanți.]

        Ne revedem la următoarea ediție!
        TXT;

        $bodyHu = <<<TXT
        {$dateHu} szombaton az udvar ismét megtelt emberekkel, illatokkal és történetekkel. Az Autentik Market {$cityHu} {$s['producers_present']} helyi termelőt és kézművest, valamint {$s['visitors_total']} Autentik kártyás látogatót hozott össze.

        A standokon ezeket találtuk: {$categoriesHu}.

        Köszönjük minden termelőnek, aki elhozta, amit maga termesztett, főzött vagy kézzel készített, és minden látogatónak, aki úgy döntött, hogy közvetlenül a forrásnál vásárol.

        [Ide írják: különleges pillanatok, új termelők, a tombola nyertesei, résztvevők idézetei.]

        Találkozunk a következő alkalommal!
        TXT;

        return BlogPost::create([
            'title_ro' => "Autentik Market {$event->city} – {$dateRo}",
            'title_hu' => "Autentik Market {$cityHu} – {$dateHu}",
            'excerpt_ro' => "{$s['producers_present']} producători locali și {$s['visitors_total']} vizitatori la ediția din {$dateRo}.",
            'excerpt_hu' => "{$s['producers_present']} helyi termelő és {$s['visitors_total']} látogató a {$dateHu} vásáron.",
            'body_ro' => $bodyRo,
            'body_hu' => $bodyHu,
            'event_id' => $event->id,
            'cover_path' => $event->cover_path,
            'generated_by' => 'template',
        ])->forceFill(['status' => BlogStatus::Draft]);
    }
}
