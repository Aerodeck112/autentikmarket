<?php

namespace Database\Seeders;

use App\Models\LayoutSlot;
use App\Models\Venue;
use App\Models\VenueLayout;
use App\Support\LayoutElements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Locațiile și hărțile OFICIALE, digitizate din schițele organizatorului
 * (Târgu Mureș — curtea Merkur, 20 de standuri; Reghin — piața Catedralei Greco-Catolice, 13 standuri).
 *
 * Coordonatele sunt păstrate în PIXELII schiței (A4, 1240×1754 px) și convertite în metri
 * presupunând standuri de 3 × 3 m. Scara reală se ajustează din CRM → Hartă & standuri → Editor.
 * Prețurile NU sunt inventate (NULL = „tarif la cerere”). Numele producătorilor din schițe nu se
 * importă aici: alocarea se face prin fluxul de rezervare.
 *
 * Idempotent: se poate rula de oricâte ori (migrație + db:seed). Nu înlocuiește un MASTER creat
 * de organizator și nu atinge evenimentele existente (au snapshot propriu).
 */
class VenueSeeder extends Seeder
{
    /** 62 px în schiță = latura unui stand de 3 m. */
    protected const PX_PER_M = 62 / 3;

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

        $rg = Venue::firstOrCreate(['code' => 'RG'], [
            'name' => 'Autentik Market Reghin',
            'slug' => 'reghin',
            'city' => 'Reghin',
            'city_hu' => 'Szászrégen',
            'county' => 'Mureș',
            'address' => 'Piața Catedralei Greco-Catolice (str. Aurel Vlaicu)',
            'address_hu' => 'A görögkatolikus székesegyház előtti tér (Aurel Vlaicu utca)',
            'description_ro' => 'Ediția din Reghin, în piața din fața Catedralei Greco-Catolice.',
            'description_hu' => 'A szászrégeni vásár a görögkatolikus székesegyház előtti téren.',
        ]);
        // Locația creată de versiunea 1.0.0 avea adresa ca placeholder: o completăm din schiță.
        if ($rg->address === '[Adresă de confirmat]') {
            $rg->update([
                'address' => 'Piața Catedralei Greco-Catolice (str. Aurel Vlaicu)',
                'address_hu' => 'A görögkatolikus székesegyház előtti tér (Aurel Vlaicu utca)',
                'description_ro' => 'Ediția din Reghin, în piața din fața Catedralei Greco-Catolice.',
                'description_hu' => 'A szászrégeni vásár a görögkatolikus székesegyház előtti téren.',
            ]);
        }

        $this->install($tm, 'Târgu Mureș — curtea Merkur (schiță oficială)', $this->targuMures());
        $this->install($rg, 'Reghin — piața Catedralei (schiță oficială)', $this->reghin());
    }

    /**
     * @param  array{origin: array{0: float, 1: float}, size: array{0: float, 1: float}, stands: array<int, array<int, mixed>>, elements: array<int, array<string, mixed>>}  $map
     */
    protected function install(Venue $venue, string $name, array $map): void
    {
        if ($venue->layouts()->where('name', $name)->exists()) {
            return;
        }

        DB::transaction(function () use ($venue, $name, $map) {
            [$ox, $oy] = $map['origin'];
            $m = fn (float $px, float $o) => round(($px - $o) / self::PX_PER_M, 2);

            // Devine MASTER doar dacă nu există unul sau dacă master-ul actual e grila DEMO din 1.0.0.
            $master = $venue->layouts()->where('is_master', true)->first();
            $makeMaster = ! $master || str_starts_with($master->name, 'Layout DEMO');

            $layout = VenueLayout::create([
                'venue_id' => $venue->id,
                'name' => $name,
                'is_master' => false,
                'width_m' => $m($map['size'][0], 0),
                'height_m' => $m($map['size'][1], 0),
                'elements' => LayoutElements::sanitize($this->toMeters($map['elements'], $ox, $oy)),
                'notes' => 'Digitizat din schița organizatorului. Scară aproximativă (stand = 3 × 3 m); ajustați după măsurători reale.',
            ]);

            foreach ($map['stands'] as [$n, $x, $y, $label]) {
                LayoutSlot::create([
                    'venue_layout_id' => $layout->id,
                    'code' => sprintf('%s-%02d', $venue->code, $n),
                    'label' => $label,
                    // (x, y) = colțul alb al pătratului din schiță (60 px); standul e centrat pe el.
                    'x' => round($m($x + 30, $ox) - 1.5, 2),
                    'y' => round($m($y + 30, $oy) - 1.5, 2),
                    'width' => 3,
                    'height' => 3,
                    'rotation' => 0,
                    'price' => null,
                    'has_electricity' => false,
                    'is_blocked' => $label !== null,
                    'notes' => $label === 'INFO' ? 'Punct de informare al organizatorului' : null,
                    'sort_order' => $n,
                ]);
            }

            if ($makeMaster) {
                $venue->layouts()->update(['is_master' => false]);
                $layout->update(['is_master' => true]);
            }
        });
    }

    /** Coordonatele (points, at, label_at) din pixeli în metri; dimensiunile (r, width, size) sunt deja în metri. */
    protected function toMeters(array $elements, float $ox, float $oy): array
    {
        $pt = fn (array $p) => [($p[0] - $ox) / self::PX_PER_M, ($p[1] - $oy) / self::PX_PER_M];

        return array_map(function (array $el) use ($pt) {
            if (isset($el['points'])) {
                $el['points'] = array_map($pt, $el['points']);
            }
            foreach (['at', 'label_at'] as $k) {
                if (isset($el[$k])) {
                    $el[$k] = $pt($el[$k]);
                }
            }

            return $el;
        }, $elements);
    }

    /** Târgu Mureș: parcarea/curtea magazinului Merkur. */
    protected function targuMures(): array
    {
        return [
            'origin' => [75, 75],
            'size' => [1090, 1605],
            // [număr, x, y, etichetă] — colțul pătratului alb din schiță.
            'stands' => [
                [1, 437, 1257, null], [2, 436, 1046, null], [3, 436, 981, 'INFO'], [4, 436, 917, null],
                [5, 436, 854, null], [6, 436, 792, null], [7, 436, 730, null], [8, 436, 667, null],
                [9, 559, 503, null], [10, 632, 503, null],
                [11, 691, 584, null], [12, 691, 647, null], [13, 691, 710, null], [14, 691, 772, null],
                [15, 691, 835, null], [16, 691, 898, null], [17, 691, 961, null],
                [18, 574, 1100, null], [19, 574, 935, null], [20, 574, 773, null],
            ],
            'elements' => [
                ['type' => 'area', 'kind' => 'asphalt', 'points' => [[75, 75], [1165, 75], [1165, 1680], [75, 1680]]],
                ['type' => 'area', 'kind' => 'green', 'points' => [
                    [75, 75], [387, 75], [363, 156], [243, 559], [219, 687], [203, 869], [196, 1061], [195, 1088],
                    [189, 1160], [172, 1210], [148, 1250], [105, 1293], [75, 1310],
                ]],
                ['type' => 'area', 'kind' => 'water', 'points' => [
                    [246, 75], [183, 75], [163, 145], [129, 200], [83, 316], [79, 519], [124, 469], [158, 414],
                    [172, 382], [184, 337], [184, 308], [192, 263], [214, 192], [246, 115],
                ]],
                ['type' => 'area', 'kind' => 'green', 'points' => [
                    [668, 192], [757, 195], [757, 545], [437, 545], [422, 620], [412, 1155], [387, 1155],
                    [392, 1000], [400, 800], [405, 621], [475, 392],
                ]],
                ['type' => 'area', 'kind' => 'path', 'points' => [
                    [512, 302], [532, 310], [540, 322], [475, 392], [405, 621], [397, 800], [387, 1100],
                    [362, 1098], [372, 615], [400, 500], [420, 420], [445, 365],
                ]],
                ['type' => 'area', 'kind' => 'green', 'points' => [
                    [386, 1256], [402, 1258], [403, 1400], [433, 1424], [451, 1426], [479, 1414], [492, 1414],
                    [508, 1430], [508, 1443], [500, 1460], [451, 1523], [676, 1675], [625, 1675], [374, 1493],
                ]],
                ['type' => 'area', 'kind' => 'building', 'points' => [[765, 215], [1162, 215], [1162, 1443], [765, 1443]],
                    'label' => ['ro' => 'Magazin Merkur', 'hu' => 'Merkúr üzlet'], 'label_at' => [963, 430], 'label_size' => 2.2],
                ['type' => 'arrow', 'kind' => 'route', 'points' => [[818, 1462], [822, 1392], [868, 1360], [990, 1352]], 'smooth' => true, 'width' => 0.25],
                ['type' => 'marker', 'symbol' => 'wc', 'at' => [1053, 1352], 'r' => 1.6],
            ],
        ];
    }

    /** Reghin: piața din fața Catedralei Greco-Catolice. */
    protected function reghin(): array
    {
        return [
            'origin' => [0, 40],
            'size' => [1240, 1540],
            'stands' => [
                [1, 528, 939, null], [2, 528, 861, 'INFO'], [3, 528, 783, null], [4, 528, 705, null],
                [5, 528, 627, null], [6, 528, 549, null], [7, 527, 468, null],
                [8, 817, 554, null], [9, 817, 632, null], [10, 817, 709, null], [11, 817, 786, null],
                [12, 817, 862, null], [13, 817, 939, null],
            ],
            'elements' => [
                ['type' => 'area', 'kind' => 'landmark', 'points' => [
                    [412, 234], [512, 162], [664, 52], [1031, 52], [1062, 127], [940, 188], [860, 281], [811, 360],
                    [835, 392], [835, 420], [815, 435], [783, 442], [675, 442], [632, 431], [586, 404], [560, 375],
                    [541, 328], [412, 273],
                ], 'label' => ['ro' => 'Catedrala Greco-Catolică', 'hu' => 'Görögkatolikus székesegyház'], 'label_at' => [712, 330], 'label_size' => 1.3],
                ['type' => 'marker', 'symbol' => 'church', 'at' => [712, 215], 'r' => 2.2],
                ['type' => 'area', 'kind' => 'green', 'points' => [
                    [20, 663], [74, 616], [146, 605], [244, 583], [291, 569], [372, 540], [431, 527], [476, 526],
                    [506, 540], [506, 985], [271, 985], [241, 887], [142, 759], [83, 704],
                ]],
                ['type' => 'area', 'kind' => 'green', 'points' => [
                    [914, 555], [962, 536], [1032, 553], [1148, 563], [1135, 681], [1118, 837], [1105, 954], [1100, 990], [914, 990],
                ]],
                ['type' => 'area', 'kind' => 'green', 'points' => [
                    [1143, 52], [1078, 52], [1058, 227], [1071, 352], [1065, 385], [1039, 457], [1046, 491], [1071, 516],
                    [1076, 513], [1119, 192], [1140, 90],
                ]],
                ['type' => 'area', 'kind' => 'green', 'points' => [
                    [496, 1383], [1082, 1383], [1031, 1423], [941, 1458], [837, 1476], [729, 1479], [639, 1467], [585, 1450], [524, 1415],
                ]],
                ['type' => 'area', 'kind' => 'road', 'points' => [
                    [0, 683], [81, 756], [182, 860], [252, 949], [271, 983], [344, 1195], [393, 1321], [438, 1419],
                    [490, 1499], [482, 1509], [400, 1558], [347, 1442], [261, 1225], [225, 1117], [209, 1037], [198, 1006],
                    [179, 970], [144, 922], [71, 845], [0, 785],
                ]],
                ['type' => 'area', 'kind' => 'paved', 'points' => [
                    [516, 453], [897, 453], [897, 1006], [1203, 1006], [1203, 1297], [1040, 1297], [1040, 1370],
                    [516, 1370], [516, 1297], [441, 1297], [441, 1150], [364, 1150], [364, 1078], [516, 1078],
                ]],
                ['type' => 'area', 'kind' => 'ground', 'points' => [[595, 1078], [822, 1078], [822, 1297], [595, 1297]]],
                ['type' => 'area', 'kind' => 'water', 'points' => [[706, 1090], [808, 1193], [706, 1297], [603, 1193]],
                    'label' => ['ro' => 'Fântână arteziană', 'hu' => 'Szökőkút'], 'label_at' => [706, 1200], 'label_size' => 0.85],
                ['type' => 'marker', 'symbol' => 'statue', 'at' => [708, 610], 'r' => 1.3],
                ['type' => 'label', 'text' => ['ro' => 'Statuia Cardinal', 'hu' => 'Alexandru Todea'], 'at' => [708, 680], 'size' => 0.8, 'tone' => 'muted'],
                ['type' => 'label', 'text' => ['ro' => 'Alexandru Todea', 'hu' => 'bíboros szobra'], 'at' => [708, 700], 'size' => 0.8, 'tone' => 'muted'],
                ['type' => 'label', 'text' => ['ro' => 'Strada Aurel Vlaicu', 'hu' => 'Aurel Vlaicu utca'], 'at' => [262, 1060], 'size' => 1.1, 'rotation' => 67, 'tone' => 'muted'],
                ['type' => 'label', 'text' => ['ro' => 'Zonă verde', 'hu' => 'Zöld övezet'], 'at' => [330, 790], 'size' => 1.1, 'tone' => 'muted'],
                ['type' => 'label', 'text' => ['ro' => 'Zonă verde', 'hu' => 'Zöld övezet'], 'at' => [1010, 790], 'size' => 1.1, 'tone' => 'muted'],
                ['type' => 'label', 'text' => ['ro' => 'Zonă verde', 'hu' => 'Zöld övezet'], 'at' => [762, 1440], 'size' => 1.1, 'tone' => 'muted'],
                ['type' => 'arrow', 'kind' => 'entrance', 'points' => [[1165, 1430], [1048, 1305]], 'width' => 1.2],
                ['type' => 'label', 'text' => ['ro' => 'Acces auto posibil', 'hu' => 'Lehetséges bejárat autóval'], 'at' => [1085, 1250], 'size' => 0.9, 'tone' => 'accent'],
            ],
        ];
    }
}
