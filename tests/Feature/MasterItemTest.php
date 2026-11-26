<?php

namespace Tests\Feature;

use App\Models\Formula;
use App\Models\Item;
use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Models\ProductionPlan;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductionPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Master Item (semua jenis dalam satu daftar), Master Satuan, dan konversi satuan beli → pakai. */
class MasterItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_master_item_lists_materials_and_finished_products_with_type_filters(): void
    {
        Material::factory()->pipe()->create(['name' => 'Pipa SS']);
        Material::factory()->semiFinished()->create(['name' => 'Pureng Mio']);
        Material::factory()->create(['name' => 'Baut L', 'warehouse_id' => Warehouse::forType(Warehouse::ACCESSORY)->id]);
        Item::create(['sku' => 'KNL-1', 'name' => 'Knalpot Mio Racing']);

        $this->get(route('admin.master-items.index'))->assertOk()
            ->assertSee('Pipa SS')->assertSee('Pureng Mio')->assertSee('Baut L')->assertSee('Knalpot Mio Racing');

        $this->get(route('admin.master-items.index', ['jenis' => 'produk-jadi']))->assertOk()
            ->assertSee('Knalpot Mio Racing')->assertDontSee('Pipa SS');
        // Pipa termasuk Bahan Baku, tidak punya tab sendiri.
        $this->get(route('admin.master-items.index', ['jenis' => 'bahan-baku']))->assertOk()
            ->assertSee('Pipa SS')->assertDontSee('Pureng Mio')
            ->assertViewHas('counts', fn ($counts) => ! $counts->has('pipa'));
        $this->get(route('admin.master-items.index', ['jenis' => 'aksesoris', 'q' => 'baut']))->assertOk()
            ->assertSee('Baut L')->assertDontSee('Pureng Mio');
    }

    public function test_plate_bought_per_sheet_is_stocked_and_used_per_square_cm(): void
    {
        $this->post(route('admin.materials.store'), [
            'name' => 'Plat SS 1 mm', 'warehouse_id' => Warehouse::forType(Warehouse::RAW)->id, 'shape' => Material::PLATE,
            'sheet_length_cm' => 240, 'sheet_width_cm' => 120,
            'unit' => 'Lembar', 'use_unit' => 'cm²', 'last_price' => 576_000, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $plate = Material::where('name', 'Plat SS 1 mm')->firstOrFail();

        // Ukuran plat terpisah; isi per lembar otomatis = 240 × 120 cm.
        $this->assertTrue($plate->isPlate());
        $this->assertEquals(28_800, (float) $plate->conversion);
        $this->assertSame('Plat SS 1 mm · 240 × 120 cm', $plate->label());

        $this->assertSame('cm²', $plate->stockUnit());
        $this->assertSame('Lembar', $plate->purchaseUnit());
        $this->assertEquals(20, $plate->unitCost(), 'Rp 576.000 ÷ 28.800 cm²');

        // Beli 2 lembar → stok 57.600 cm² dengan harga Rp 20/cm².
        $this->post(route('admin.purchases.store'), [
            'purchased_at' => '2026-11-26',
            'items' => [['material_id' => $plate->id, 'qty' => 2, 'unit_price' => 576_000]],
        ])->assertSessionHasNoErrors();
        $plate->refresh();
        $this->assertEquals(57_600, (float) $plate->stock);
        $this->assertEquals(20, (float) $plate->avg_cost);
        $this->assertEquals(1_152_000, (float) MaterialPurchase::firstOrFail()->total);

        // Formula memakai 1.200 cm² per knalpot → Rp 24.000.
        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'matic', 'motor_type' => 'Mio', 'name' => 'Mio Plat', 'is_active' => 1,
            'parts' => [['name' => 'Silincer', 'components' => [['name' => 'Tabung', 'qty' => 1, 'materials' => [['material_id' => $plate->id, 'qty' => 1_200]]]]]],
        ])->assertSessionHasNoErrors();
        $formula = Formula::firstOrFail();
        $this->assertEquals(24_000, $formula->materialCostPerUnit());

        // 50 knalpot = 60.000 cm²; ada 57.600 → kurang 2.400 cm² = dibulatkan jadi 1 lembar.
        $this->post(route('admin.plans.store'), ['planned_for' => '2026-12-01', 'lines' => [['formula_id' => $formula->id, 'qty' => 50]]]);
        $row = app(ProductionPlanner::class)->forPlan(ProductionPlan::firstOrFail())['rows']->first();
        $this->assertEquals(2_400, $row['short']);
        $this->assertEquals(1, $row['buy_qty']);
        $this->assertSame('Lembar', $row['buy_unit']);

        // Konversi tidak bisa diubah setelah ada stok.
        $this->put(route('admin.materials.update', $plate), [
            'name' => 'Plat SS 1 mm', 'warehouse_id' => $plate->warehouse_id, 'measure' => Material::UNIT,
            'unit' => 'Lembar', 'use_unit' => 'cm²', 'conversion' => 10_000, 'is_active' => 1,
        ])->assertSessionHas('error');
        $this->assertEquals(28_800, (float) $plate->fresh()->conversion);
    }

    public function test_conversion_is_required_when_the_use_unit_differs(): void
    {
        $this->post(route('admin.materials.store'), [
            'name' => 'Plat', 'warehouse_id' => Warehouse::forType(Warehouse::RAW)->id, 'measure' => Material::UNIT,
            'unit' => 'Lembar', 'use_unit' => 'cm²', 'is_active' => 1,
        ])->assertSessionHasErrors('conversion');

        $this->post(route('admin.materials.store'), [
            'name' => 'Baut', 'warehouse_id' => Warehouse::forType(Warehouse::RAW)->id, 'measure' => Material::UNIT,
            'unit' => 'TIDAKADA', 'is_active' => 1,
        ])->assertSessionHasErrors('unit');
    }

    public function test_units_can_be_added_and_a_unit_in_use_cannot_be_deleted(): void
    {
        $this->post(route('admin.units.store'), ['name' => 'Rim'])->assertSessionHasNoErrors();
        $this->post(route('admin.units.store'), ['name' => 'Rim'])->assertSessionHasErrors('name');
        $this->get(route('admin.units.index'))->assertOk()->assertSee('Rim');

        Material::factory()->create(['unit' => 'Lembar']);
        $this->delete(route('admin.units.destroy', Unit::where('name', 'Lembar')->firstOrFail()))->assertSessionHas('error');
        $this->assertDatabaseHas('units', ['name' => 'Lembar']);

        $this->delete(route('admin.units.destroy', Unit::where('name', 'Rim')->firstOrFail()))->assertSessionMissing('error');
        $this->assertDatabaseMissing('units', ['name' => 'Rim']);
    }

    public function test_pipe_size_is_entered_as_diameter_thickness_and_bar_length(): void
    {
        $this->post(route('admin.materials.store'), [
            'name' => 'Pipa SS 201', 'warehouse_id' => Warehouse::forType(Warehouse::RAW)->id, 'shape' => Material::PIPE,
            'diameter' => '1¼"', 'thickness_mm' => 1.2, 'bar_length_cm' => 600, 'min_reusable_cm' => 5, 'last_price' => 300_000, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $pipe = Material::where('name', 'Pipa SS 201')->firstOrFail();

        $this->assertTrue($pipe->isPipe());
        $this->assertSame('1¼"', $pipe->diameter);
        $this->assertEquals(1.2, (float) $pipe->thickness_mm);
        $this->assertEquals(600, (float) $pipe->bar_length_cm);
        $this->assertSame('Pipa SS 201 · Ø 1¼" · tebal 1,2 mm', $pipe->label());

        // Form ubah menampilkan kolom-kolomnya, bukan satu teks.
        $this->get(route('admin.materials.edit', $pipe))->assertOk()->assertSee('name="diameter"', false)->assertSee('name="sheet_width_cm"', false);
    }
}
