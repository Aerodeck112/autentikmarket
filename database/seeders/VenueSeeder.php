<?php

namespace Database\Seeders;

use App\Models\LayoutSlot;
use App\Models\Venue;
use App\Models\VenueLayout;
use Illuminate\Database\Seeder;

/**
 * Locații inițiale + layout-uri master PROVIZORII (grilă generică).
 * Se înlocuiesc după digitizarea schițelor reale (CRM → Hartă & standuri → Editor).
 * Prețurile NU sunt inventate: rămân NULL („tarif la cerere”) până la configurare.
 */
class VenueSeeder extends Seeder
{
    public function run(): void
    {
        $tm = Venue::firstOrCreate(['code' => 'TM'], [
            'name' => 'Autentik Market Târgu Mureș — curtea Merkur',
            'slug' => 'targu-mures-merkur',
            'city' => 'Târgu Mureș',
            'city_hu' => 'Marosvásárhely',
            'county' => 'Mureș',
            'address' => 'Str. Livezeni nr. 7 (curtea magazinului Merkur, cartierul Tudor)',
            'address_hu' => 'Jeddi út 7. (a Merkúr üzlet udvara, Tudor negyed)',
            'description_ro' => 'Locația ediției lunare din Târgu Mureș.',
            'description_hu' => 'A marosvásárhelyi havi vásár helyszíne.',
        ]);
        $this->gridLayout($tm, 'TM', 'Layout DEMO Târgu Mureș — înlocuiți după schiță', 42, 30, 3, 3, 12, 2);

        $rg = Venue::firstOrCreate(['code' => 'RG'], [
            'name' => 'Autentik Market Reghin',
            'slug' => 'reghin',
            'city' => 'Reghin',
            'city_hu' => 'Szászrégen',
            'county' => 'Mureș',
            'address' => '[Adresă de confirmat]',
            'address_hu' => '[Cím megerősítendő]',
            'description_ro' => 'Locație în pregătire — schița va fi digitizată.',
            'description_hu' => 'Előkészítés alatt álló helyszín — a vázlat digitalizálásra kerül.',
        ]);
        $this->gridLayout($rg, 'RG', 'Layout DEMO Reghin — înlocuiți după schiță', 30, 20, 3, 3, 7, 2);
    }

    /**
     * Grilă: N standuri pe fiecare dintre cele `$rows` rânduri, cu culoar central de 4 m.
     */
    protected function gridLayout(Venue $venue, string $prefix, string $name, float $w, float $h, float $standW, float $standH, int $perRow, int $rows): void
    {
        if ($venue->layouts()->exists()) {
            return;
        }
        $layout = VenueLayout::create([
            'venue_id' => $venue->id, 'name' => $name, 'is_master' => true,
            'width_m' => $w, 'height_m' => $h, 'notes' => 'Layout provizoriu generat automat. Digitizați schița reală.',
        ]);

        $n = 1;
        $gap = 0.5;
        $startX = ($w - ($perRow * $standW + ($perRow - 1) * $gap)) / 2;
        $rowYs = [$h / 2 - 2 - $standH, $h / 2 + 2]; // culoar central de 4 m
        for ($r = 0; $r < $rows; $r++) {
            for ($i = 0; $i < $perRow; $i++) {
                LayoutSlot::create([
                    'venue_layout_id' => $layout->id,
                    'code' => sprintf('%s-%02d', $prefix, $n),
                    'x' => round($startX + $i * ($standW + $gap), 2),
                    'y' => round($rowYs[$r] ?? ($r * ($standH + 4)), 2),
                    'width' => $standW,
                    'height' => $standH,
                    'rotation' => 0,
                    'price' => null,
                    'has_electricity' => $i % 3 === 0,
                    'sort_order' => $n,
                ]);
                $n++;
            }
        }
    }
}
