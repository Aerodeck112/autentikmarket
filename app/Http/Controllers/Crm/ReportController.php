<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Export\TableExporter;
use App\Services\Stats\StatisticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Rapoarte, inclusiv RAPORTARE PE LIMBI (RO/HU) per eveniment și total. */
class ReportController extends Controller
{
    public function index(Request $request, StatisticsService $stats): View
    {
        $city = $request->input('city');

        return view('crm.reports.index', [
            'dashboard' => $stats->dashboard(),
            'rows' => $stats->languageReport($city),
            'cities' => Event::distinct()->pluck('city'),
            'city' => $city,
        ]);
    }

    public function exportLanguages(Request $request, StatisticsService $stats, TableExporter $exporter): StreamedResponse
    {
        $rows = collect($stats->languageReport($request->input('city')))->map(fn ($r) => [
            $r['event']->title_ro, $r['event']->event_date->toDateString(), $r['event']->city,
            $r['visitors_total'], $r['visitors_ro'], $r['visitors_hu'], $r['visitors_new'], $r['visitors_returning'],
            $r['producers_accepted'], $r['producers_ro'], $r['producers_hu'], $r['producers_present'], $r['producers_absent'],
            $r['slots_total'], $r['slots_occupied'], $r['occupancy_pct'], $r['interactions'], $r['reviews'], $r['rating'],
            $r['raffle_participants'], $r['rewards_redeemed'],
        ]);

        return $exporter->download('raport-evenimente-'.now()->format('Ymd'), [
            __('Eveniment'), __('Data'), __('Oraș'), __('Vizitatori'), __('Vizitatori RO'), __('Vizitatori HU'), __('Vizitatori noi'), __('Vizitatori recurenți'),
            __('Producători acceptați'), __('Producători RO'), __('Producători HU'), __('Producători prezenți'), __('Producători absenți'),
            __('Standuri'), __('Standuri ocupate'), __('Grad ocupare %'), __('Interacțiuni'), __('Recenzii'), __('Rating'),
            __('Participanți tombolă'), __('Recompense ridicate'),
        ], $rows, $request->input('format', 'csv'));
    }
}
