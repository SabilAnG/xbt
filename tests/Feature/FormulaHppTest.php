<?php

namespace Tests\Feature;

use App\Models\CostComponent;
use App\Models\Formula;
use App\Models\Item;
use App\Models\Machine;
use App\Models\Material;
use App\Models\OverheadItem;
use App\Models\User;
use Database\Seeders\HppMasterSeeder;
use Database\Seeders\KnalpotWorkbookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormulaHppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        $this->seed(HppMasterSeeder::class);
    }

    public function test_formula_with_dimensional_material_process_and_machine_is_saved_and_costed(): void
    {
        $pipe = Material::create([
            'sku' => 'PIPA-32', 'name' => 'Pipa 32', 'unit' => 'batang', 'dimension_type' => 'linear',
            'length_mm' => 6000, 'cost_price' => 240_000,
        ]);
        $weld = CostComponent::where('slug', 'las-header')->firstOrFail();
        $machine = Machine::where('code', 'LAS')->firstOrFail();

        $this->post(route('admin.formulas.store'), [
            'name' => 'Mio Racing V1',
            'output_qty' => 1,
            'output_unit' => 'set',
            'is_active' => 1,
            'materials' => [[
                'material_id' => $pipe->id, 'bom_group' => 'header', 'input_mode' => 'length',
                'piece_length_mm' => 600, 'piece_count' => 2, 'waste_percent' => 0,
            ]],
            'costs' => [['cost_component_id' => $weld->id, 'bom_group' => 'header', 'minutes' => 30]],
            'machines' => [['machine_id' => $machine->id, 'bom_group' => 'header', 'minutes' => 30]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $formula = Formula::where('code', 'MIO-RACING-V1')->firstOrFail();
        $line = $formula->materials->first();

        // 2 potong × 600 mm = 1.200 mm pipa.
        $this->assertEquals(1200, (float) $line->qty);
        $this->assertCount(1, $formula->costs);
        $this->assertCount(1, $formula->machines);

        $formula->load('materials.material', 'costs.component', 'machines.machine');
        $this->assertGreaterThan(0, $formula->serviceCost());
        $this->assertGreaterThan(0, $formula->machineCost());
        $this->assertEqualsWithDelta(
            $formula->materialCost() + $formula->serviceCost() + $formula->machineCost() + $formula->overheadCost(),
            $formula->totalCost(),
            0.01,
        );

        $this->get(route('admin.formulas.edit', [$formula, 'tab' => 'hasil']))->assertOk()
            ->assertSee('Kartu harga pokok produksi')
            ->assertSee(rupiah($formula->hppPerUnit()))
            ->assertSee('Retail / Normal');
        $this->get(route('admin.formulas.index'))->assertOk()->assertSee('Mio Racing V1');
    }

    public function test_the_same_material_twice_is_rejected(): void
    {
        $material = Material::create(['sku' => 'B1', 'name' => 'Baut', 'unit' => 'Pcs', 'dimension_type' => 'count']);

        $this->post(route('admin.formulas.store'), [
            'name' => 'Dobel', 'output_qty' => 1, 'output_unit' => 'pcs',
            'materials' => [
                ['material_id' => $material->id, 'input_mode' => 'direct', 'qty' => 1],
                ['material_id' => $material->id, 'input_mode' => 'direct', 'qty' => 2],
            ],
        ])->assertSessionHasErrors('materials');
    }

    public function test_hpp_is_per_brand_and_saving_a_workbook_recipe_keeps_stock_identical(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);
        $formula = Formula::where('name', 'MIO STAINLESS')->with('materials.material', 'costs.component', 'machines.machine')->firstOrFail();
        $before = Material::orderBy('id')->pluck('stock', 'id')->map(fn ($v) => round((float) $v, 3));

        // FD hanya memakai baris FD + baris umum, bukan baris XBT.
        $this->assertLessThan($formula->hppPerUnit(), $formula->forBrand('FD')->hppPerUnit());
        $this->assertSame(['FD', 'XBT'], $formula->brands()->all());

        $this->get(route('admin.formulas.edit', [$formula, 'tab' => 'hasil', 'merek' => 'XBT']))->assertOk()->assertSee('HPP untuk merek');

        $this->put(route('admin.formulas.update', $formula), [
            'name' => $formula->name, 'code' => $formula->code, 'output_qty' => 1, 'output_unit' => 'pcs', 'is_active' => 1,
            'materials' => $formula->materials->map(fn ($line) => [
                'material_id' => $line->material_id, 'brands' => $line->brandList(), 'bom_group' => $line->bom_group,
                'input_mode' => 'direct', 'qty' => (float) $line->qty, 'piece_count' => 1, 'waste_percent' => 0,
            ])->all(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $after = Material::orderBy('id')->pluck('stock', 'id')->map(fn ($v) => round((float) $v, 3));
        $this->assertEquals($before->all(), $after->all());
        $this->assertEquals(53, (float) Item::sum('stock'));
    }

    public function test_master_pages_and_production_settings_work(): void
    {
        foreach (['komponen-biaya', 'mesin', 'overhead', 'tingkatan-harga', 'type-motor', 'merek-motor'] as $master) {
            $this->get(route('admin.masters.index', $master))->assertOk();
            $this->get(route('admin.masters.create', $master))->assertOk();
        }

        $this->post(route('admin.masters.store', 'tingkatan-harga'), ['name' => 'Grosir Besar', 'margin_percent' => 15, 'fee_percent' => 0, 'is_active' => 1])
            ->assertRedirect(route('admin.masters.index', 'tingkatan-harga'));
        $this->assertDatabaseHas('price_tiers', ['slug' => 'grosir-besar', 'margin_percent' => 15]);

        $this->put(route('admin.production-settings.update'), [
            'tarif_listrik' => 1700, 'kerf_mm' => 2, 'target_produksi_bulanan' => 80, 'pembulatan_harga' => 500,
        ])->assertRedirect();
        $this->assertEquals(80, OverheadItem::targetPerMonth());
        $this->get(route('admin.production-settings.edit'))->assertOk();
    }
}
