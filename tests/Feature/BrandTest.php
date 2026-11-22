<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Formula;
use App\Models\Item;
use App\Models\Material;
use App\Models\User;
use App\Services\DashboardSummary;
use App\Services\InventoryService;
use Database\Seeders\KnalpotWorkbookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_workbook_brands_become_master_brands_with_stats(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);

        $this->assertSame(['FD', 'XBT'], Brand::names()->all());

        $this->get(route('admin.brands.index'))->assertOk()->assertSee('XBT')->assertSee('FD');
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Ringkasan per merek');

        // 53 unit stok = FD + XBT (produk tanpa merek bersisa 0).
        $stats = app(DashboardSummary::class)->byBrand()->keyBy('name');
        $this->assertEquals(53, $stats['FD']['stock'] + $stats['XBT']['stock']);
    }

    public function test_a_new_brand_can_be_added_and_used_on_products_and_recipe_lines(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);

        $this->post(route('admin.brands.store'), ['name' => 'ABC', 'code' => 'ABC', 'color' => '#2563eb', 'is_active' => 1])
            ->assertRedirect(route('admin.brands.index'));

        $formula = Formula::where('name', 'MIO STAINLESS')->firstOrFail();
        $shield = $formula->materials()->whereHas('material', fn ($q) => $q->where('name', 'Tameng FD'))->firstOrFail();
        // Baris "Tameng FD" kini juga berlaku untuk merek ABC.
        $shield->update(['brand' => 'FD,ABC']);

        $item = Item::create(['sku' => 'KNL-ABC', 'name' => 'Mio ABC', 'brand' => 'ABC', 'formula_id' => $formula->id, 'cost_price' => 450_000]);
        $before = (float) Material::where('name', 'Tameng FD')->value('stock');
        $xbtBefore = (float) Material::where('name', 'Tameng XBT')->value('stock');

        app(InventoryService::class)->recordProduction($item, 2, '2026-11-20');

        $this->assertEquals($before - 2, (float) Material::where('name', 'Tameng FD')->value('stock'));
        $this->assertEquals($xbtBefore, (float) Material::where('name', 'Tameng XBT')->value('stock'));

        // Produk tidak boleh memakai merek yang belum terdaftar.
        $this->post(route('admin.items.store'), ['sku' => 'X1', 'model_motor' => 'Mio', 'brand' => 'TIDAKADA', 'cost_price' => 1, 'sell_price' => 1, 'sell_price_online' => 1, 'min_stock' => 0])
            ->assertSessionHasErrors('brand');
    }

    public function test_renaming_a_brand_updates_products_and_recipe_lines(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);
        $brand = Brand::where('name', 'XBT')->firstOrFail();
        $count = Item::where('brand', 'XBT')->count();

        $this->put(route('admin.brands.update', $brand), ['name' => 'XBT Racing', 'code' => 'XBT', 'color' => '#ea580c', 'is_active' => 1])
            ->assertRedirect(route('admin.brands.index'));

        $this->assertSame(0, Item::where('brand', 'XBT')->count());
        $this->assertSame($count, Item::where('brand', 'XBT Racing')->count());
        $this->assertDatabaseHas('formula_materials', ['brand' => 'XBT Racing']);
        $this->assertDatabaseMissing('formula_materials', ['brand' => 'XBT']);

        // Brand yang masih dipakai tidak bisa dihapus.
        $this->delete(route('admin.brands.destroy', $brand))->assertSessionHas('error');
        $this->assertDatabaseHas('brands', ['name' => 'XBT Racing']);
    }

    public function test_recipe_form_saves_multiple_brands_per_line(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);
        Brand::create(['name' => 'ABC']);
        $formula = Formula::where('name', 'MATIC BESAR')->with('materials')->firstOrFail();

        $lines = $formula->materials->map(fn ($line) => [
            'material_id' => $line->material_id, 'brands' => $line->brandList(), 'bom_group' => $line->bom_group,
            'input_mode' => 'direct', 'qty' => (float) $line->qty, 'piece_count' => 1, 'waste_percent' => 0,
        ])->all();
        $lines[0]['brands'] = ['FD', 'ABC'];

        $this->put(route('admin.formulas.update', $formula), [
            'name' => $formula->name, 'code' => $formula->code, 'output_qty' => 1, 'output_unit' => 'pcs', 'is_active' => 1,
            'materials' => $lines,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('FD,ABC', $formula->materials()->orderBy('sort_order')->first()->brand);
        $this->get(route('admin.formulas.edit', [$formula, 'tab' => 'bahan']))->assertOk()->assertSee('ABC');
    }
}
