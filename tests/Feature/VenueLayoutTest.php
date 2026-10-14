<?php

namespace Tests\Feature;

use App\Models\Venue;
use App\Models\VenueLayout;
use Database\Seeders\VenueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hărțile oficiale (digitizate din schițe) + decorul vectorial al layout-ului.
 */
class VenueLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_layouts_match_the_sketches(): void
    {
        $tm = Venue::where('code', 'TM')->firstOrFail()->layouts()->where('is_master', true)->firstOrFail();
        $rg = Venue::where('code', 'RG')->firstOrFail()->layouts()->where('is_master', true)->firstOrFail();

        $this->assertSame(20, $tm->slots()->count());
        $this->assertSame(13, $rg->slots()->count());
        $this->assertSame(['TM-03'], $tm->slots()->where('is_blocked', true)->pluck('code')->all(), 'standul 3 = INFO');
        $this->assertSame(['RG-02'], $rg->slots()->where('is_blocked', true)->pluck('code')->all(), 'standul 2 = INFO');
        $this->assertSame(0, $tm->slots()->whereNotNull('price')->count(), 'prețurile nu sunt inventate');

        // Standurile se află în interiorul terenului și nu se suprapun.
        foreach ([$tm, $rg] as $layout) {
            $slots = $layout->slots()->get();
            foreach ($slots as $s) {
                $this->assertGreaterThanOrEqual(0, $s->x);
                $this->assertLessThanOrEqual($layout->width_m, $s->x + $s->width);
                $this->assertLessThanOrEqual($layout->height_m, $s->y + $s->height);
                foreach ($slots as $o) {
                    if ($o->id <= $s->id) {
                        continue;
                    }
                    $overlap = min($s->x + $s->width, $o->x + $o->width) - max($s->x, $o->x) > 0.2
                        && min($s->y + $s->height, $o->y + $o->height) - max($s->y, $o->y) > 0.2;
                    $this->assertFalse($overlap, "{$s->code} se suprapune cu {$o->code}");
                }
            }
        }

        $kinds = collect($tm->elements)->pluck('kind')->filter()->unique()->values()->all();
        $this->assertContains('building', $kinds); // magazinul Merkur
        $this->assertContains('water', $kinds);
        $this->assertTrue(collect($rg->elements)->contains(fn ($e) => ($e['symbol'] ?? null) === 'church'));
    }

    public function test_seeder_is_idempotent_and_keeps_a_custom_master(): void
    {
        $venue = Venue::where('code', 'TM')->firstOrFail();
        $custom = $venue->layouts()->create(['name' => 'Layout organizator', 'width_m' => 40, 'height_m' => 30]);
        $venue->layouts()->update(['is_master' => false]);
        $custom->update(['is_master' => true]);

        (new VenueSeeder)->run();
        (new VenueSeeder)->run();

        $this->assertSame(2, $venue->layouts()->count());
        $this->assertTrue($custom->fresh()->is_master);
    }

    public function test_upgrade_from_1_0_0_replaces_the_demo_master(): void
    {
        $venue = Venue::where('code', 'TM')->firstOrFail();
        $venue->layouts()->delete();
        $demo = $venue->layouts()->create(['name' => 'Layout DEMO Târgu Mureș — înlocuiți după schiță', 'width_m' => 42, 'height_m' => 30, 'is_master' => true]);

        (new VenueSeeder)->run();

        $this->assertFalse($demo->fresh()->is_master, 'grila DEMO rămâne (non-distructiv), dar nu mai e MASTER');
        $this->assertSame(20, $venue->layouts()->where('is_master', true)->firstOrFail()->slots()->count());
    }

    public function test_event_snapshot_and_public_map_carry_the_decor(): void
    {
        $event = $this->openEvent();

        $this->assertNotEmpty($event->layout->elements);
        $this->getJson(lroute('events.map', $event->slug))
            ->assertOk()
            ->assertJsonPath('layout.elements.0.type', 'area')
            ->assertJsonCount(20, 'slots');
    }

    public function test_editor_saves_validated_decor_and_keeps_it_when_omitted(): void
    {
        $this->actingAs($this->admin());
        $layout = VenueLayout::where('is_master', true)->whereHas('venue', fn ($q) => $q->where('code', 'RG'))->firstOrFail();
        $payload = fn (array $extra = []) => [
            'layout' => ['width_m' => $layout->width_m, 'height_m' => $layout->height_m, 'background_opacity' => 0.6, 'name' => $layout->name],
            'slots' => $layout->slots()->get()->map(fn ($s) => [
                'id' => $s->id, 'code' => $s->code, 'label' => $s->label, 'x' => $s->x, 'y' => $s->y,
                'width' => $s->width, 'height' => $s->height, 'rotation' => 0, 'is_blocked' => $s->is_blocked,
            ])->all(),
        ] + $extra;

        $this->putJson(route('crm.layouts.sync', $layout), $payload())->assertOk();
        $this->assertCount(19, $layout->fresh()->elements, 'fără cheia elements decorul rămâne neatins');

        $this->putJson(route('crm.layouts.sync', $layout), $payload(['elements' => [['type' => 'marker', 'symbol' => 'bomb', 'at' => [1, 1]]]]))
            ->assertStatus(422)->assertJsonValidationErrors('elements');

        $this->putJson(route('crm.layouts.sync', $layout), $payload(['elements' => [['type' => 'label', 'at' => [5, 5], 'text' => '<b>Intrare</b>']]]))
            ->assertOk()
            ->assertJsonPath('layout.elements.0.text.ro', '<b>Intrare</b>'); // stocat ca text; escapat la randare
        $this->assertCount(1, $layout->fresh()->elements);
    }
}
