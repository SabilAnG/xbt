<?php

namespace Tests\Feature;

use App\Models\Formula;
use App\Models\Material;
use App\Models\ProductionPlan;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductionPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Barang setengah jadi yang bisa dibuat sendiri sekaligus dibeli (mis. tabung silincer). */
class MakeOrBuyTest extends TestCase
{
    use RefreshDatabase;

    private Material $tube;

    private Formula $tubeFormula;

    private ProductionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());

        $plate = Material::factory()->create(['name' => 'Plat SS', 'last_price' => 20_000]);
        $this->tube = Material::factory()->semiFinished(95_000)->create(['name' => 'Tabung Silincer Mio', 'preferred_source' => 'buat']);

        $this->tubeFormula = Formula::create(['code' => 'F-TBG', 'name' => 'Tabung Silincer Mio', 'output_type' => Formula::SEMI_FINISHED, 'output_material_id' => $this->tube->id]);
        $this->tubeFormula->parts()->create(['name' => 'Silincer', 'sort_order' => 1])
            ->components()->create(['name' => 'Tabung', 'sort_order' => 1])
            ->materials()->create(['material_id' => $plate->id, 'qty' => 4]);

        $exhaust = Formula::create(['code' => 'F-KNL', 'name' => 'Knalpot Mio', 'output_type' => Formula::KNALPOT]);
        $exhaust->parts()->create(['name' => 'Silincer', 'sort_order' => 1])
            ->components()->create(['name' => 'Tabung', 'sort_order' => 1])
            ->materials()->create(['material_id' => $this->tube->id, 'qty' => 1]);

        $this->plan = ProductionPlan::create(['plan_number' => 'RP-TEST', 'planned_for' => '2026-12-10']);
        $this->plan->lines()->create(['formula_id' => $exhaust->id, 'qty' => 15]);
    }

    /** @return array<string, mixed> */
    private function tubeRow(): array
    {
        return app(ProductionPlanner::class)->forPlan($this->plan->fresh())['rows']->firstWhere('material.id', $this->tube->id);
    }

    public function test_shortage_can_be_split_between_making_and_buying(): void
    {
        // Sumber utama "buat": semua kekurangan disarankan dibuat.
        $row = $this->tubeRow();
        $this->assertTrue($row['split']);
        $this->assertEquals(15, $row['make_qty']);
        $this->assertEquals(0, $row['buy_qty']);
        $this->assertEquals(80_000, $row['make_unit_cost'], '4 × plat Rp 20.000');
        $this->assertEquals(95_000, $row['buy_unit_cost']);

        // 5 dibeli, 10 dibuat.
        $this->post(route('admin.plans.source', $this->plan), ['material_id' => $this->tube->id, 'buy_qty' => 5])->assertSessionHasNoErrors();
        $row = $this->tubeRow();
        $this->assertEquals(10, $row['make_qty']);
        $this->assertEquals(5, $row['buy_qty']);
        $this->assertEquals(5 * 95_000, $row['buy_cost']);

        // Tambah formula tabung 10 ke rencana: tidak ada lagi yang perlu dibuat, beli tetap 5.
        $this->get(route('admin.plans.show', $this->plan))->assertOk()->assertSee('+ Buat 10 lewat formula');
        $this->post(route('admin.plans.add-formula', $this->plan), ['formula_id' => $this->tubeFormula->id, 'qty' => 10])->assertSessionHasNoErrors();
        $row = $this->tubeRow();
        $this->assertEquals(10, $row['planned']);
        $this->assertEquals(0, $row['make_qty']);
        $this->assertEquals(5, $row['buy_qty']);

        // "Beli kekurangan" membawa 5 tabung ke form pembelian.
        $this->get(route('admin.purchases.create', ['rencana' => $this->plan->id]))->assertOk()
            ->assertViewHas('lines', fn (array $lines) => collect($lines)->firstWhere('material_id', $this->tube->id)['qty'] == 5);
    }

    public function test_flags_restrict_where_an_item_can_come_from(): void
    {
        // Hanya diproduksi: tidak pernah disarankan beli, dan ditolak di pembelian.
        $this->tube->update(['is_purchasable' => false]);
        $row = $this->tubeRow();
        $this->assertFalse($row['split']);
        $this->assertEquals(15, $row['make_qty']);
        $this->assertEquals(0, $row['buy_qty']);

        $this->get(route('admin.purchases.create'))->assertOk()
            ->assertViewHas('materials', fn ($materials) => ! $materials->contains('id', $this->tube->id));
        $this->post(route('admin.purchases.store'), [
            'purchased_at' => '2026-12-01', 'items' => [['material_id' => $this->tube->id, 'qty' => 1, 'unit_price' => 95_000]],
        ])->assertSessionHas('error');

        // Hasil formula tidak bisa ditandai "tidak bisa diproduksi".
        $this->put(route('admin.materials.update', $this->tube), [
            'name' => 'Tabung Silincer Mio', 'warehouse_id' => Warehouse::forType(Warehouse::SEMI)->id, 'measure' => 'unit', 'unit' => 'Pcs',
            'is_purchasable' => 1, 'is_producible' => 0, 'is_active' => 1,
        ])->assertSessionHasErrors('is_producible');

        // Tidak boleh diproduksi: formula tidak dipakai, kekurangan dibeli.
        $this->tube->update(['is_purchasable' => true, 'is_producible' => false]);
        $row = $this->tubeRow();
        $this->assertFalse($row['can_make']);
        $this->assertEquals(0, $row['make_qty']);
        $this->assertEquals(15, $row['buy_qty']);
        $this->assertSame('Beli', $this->tube->fresh()->sourcingLabel());
    }
}
