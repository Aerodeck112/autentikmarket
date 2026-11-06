<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Models\ProducerCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Producător în mai multe categorii + județul afișat pe site. */
class ProducerCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_puts_producer_in_several_categories(): void
    {
        $this->seed();
        [$honey, $cheese, $crafts] = ProducerCategory::orderBy('sort_order')->take(3)->get()->all();
        $p = $this->approvedProducer();
        $p->update(['is_public' => true, 'city' => 'Reghin', 'county' => 'Mureș']);

        $this->actingAs($this->admin('organizer'))->put(route('crm.producers.update', $p), [
            'brand_name' => $p->brand_name, 'preferred_language' => 'ro', 'city' => 'Reghin', 'county' => 'Mureș', 'is_public' => '1',
            'categories_form' => '1', 'category_ids' => [$cheese->id, $honey->id],
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertEqualsCanonicalizing([$honey->id, $cheese->id], $p->categories->pluck('id')->all());
        $this->assertSame($honey->id, $p->producer_category_id, 'principală = prima în ordinea categoriilor');

        // site: apare în ambele categorii, nu și în a treia; județul e afișat
        $this->get(lroute('producers.index', ['category' => $cheese->slug], 'ro'))->assertSee($p->brand_name);
        $this->get(lroute('producers.index', ['category' => $honey->slug], 'ro'))->assertSee($p->brand_name);
        $this->get(lroute('producers.index', ['category' => $crafts->slug], 'ro'))->assertDontSee($p->brand_name);
        $this->get(lroute('producers.index', [], 'ro'))->assertSee('Reghin, jud. Mureș')->assertSee('+1');
        $this->get(lroute('producers.index', ['county' => 'Mureș'], 'ro'))->assertSee($p->brand_name);
        $this->get(lroute('producers.index', ['county' => 'Cluj'], 'ro'))->assertDontSee($p->brand_name);
        $this->get(lroute('producers.show', $p, 'hu'))->assertOk()->assertSee('Reghin, Mureș megye')->assertSee($cheese->name_hu);

        // CRM: filtrul pe categorie folosește toate categoriile; categoria cu producători nu se șterge
        $this->get(route('crm.producers.index', ['category' => $cheese->id]))->assertSee($p->brand_name);
        $this->delete(route('crm.categories.destroy', $cheese))->assertSessionHas('error');

        // debifând categoria principală, următoarea devine principală
        $this->put(route('crm.producers.update', $p), [
            'brand_name' => $p->brand_name, 'preferred_language' => 'ro', 'categories_form' => '1', 'category_ids' => [$cheese->id],
        ]);
        $this->assertSame($cheese->id, $p->fresh()->producer_category_id);
    }

    public function test_producer_chooses_categories_in_portal_and_primary_is_kept_in_the_list(): void
    {
        $this->seed();
        [$a, $b] = ProducerCategory::orderBy('sort_order')->take(2)->get()->all();
        $p = Producer::factory()->approved()->create(['producer_category_id' => $b->id]);
        $this->assertSame([$b->id], $p->categories()->pluck('producer_categories.id')->all(), 'categoria principală intră automat în listă');

        $this->actingAs($p->user)->put(route('producer.profile.update'), [
            'brand_name' => $p->brand_name, 'categories_form' => '1', 'category_ids' => [$a->id, $b->id],
        ])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertCount(2, $p->categories);
        $this->assertSame($b->id, $p->producer_category_id, 'principala rămâne cât e bifată');
    }
}
