<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Models\User;
use Database\Seeders\ComponentSeeder;
use Database\Seeders\KnalpotWorkbookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentWarehouseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        $this->seed([KnalpotWorkbookSeeder::class, ComponentSeeder::class]);
    }

    public function test_component_with_materials_can_be_created_and_listed(): void
    {
        [$pipe, $plate] = Material::query()->where('stock', '>', 10)->take(2)->get()->all();

        $this->post(route('admin.components.store'), [
            'code' => 'KMP-TEST', 'name' => 'Leher Racing', 'group' => 'leher', 'unit' => 'Set', 'is_active' => 1,
            'lines' => [
                ['material_id' => $pipe->id, 'qty' => 1],
                ['material_id' => $plate->id, 'qty' => 0.25],
                ['material_id' => '', 'qty' => ''],
            ],
        ])->assertRedirect();

        $component = Component::where('code', 'KMP-TEST')->firstOrFail();
        $this->assertCount(2, $component->materials);

        $this->get(route('admin.components.index'))->assertOk()->assertSee('Leher Racing')->assertSee($pipe->name);
        $this->get(route('admin.components.show', $component))->assertOk();
        $this->get(route('admin.components.edit', $component))->assertOk();

        $this->post(route('admin.components.store'), [
            'code' => 'KMP-TEST', 'name' => 'Duplikat', 'group' => 'leher', 'unit' => 'Pcs',
            'lines' => [['material_id' => $pipe->id, 'qty' => 1], ['material_id' => $pipe->id, 'qty' => 2]],
        ])->assertSessionHasErrors(['code', 'lines.1.material_id']);
    }

    public function test_making_components_moves_semi_finished_stock_and_consumes_raw_materials(): void
    {
        $material = Material::query()->where('stock', '>', 10)->firstOrFail();
        $before = (float) $material->stock;
        $component = Component::where('code', 'KMP-LHR')->firstOrFail();
        $component->materials()->create(['material_id' => $material->id, 'qty' => 2]);

        $this->post(route('admin.warehouses.movements.store'), [
            'component_id' => $component->id, 'type' => 'masuk', 'qty' => 3, 'moved_at' => '2026-11-22', 'consume_materials' => 1,
        ])->assertRedirect(route('admin.warehouses.show', 'setengah-jadi'));

        $this->assertSame(3.0, (float) $component->refresh()->stock);
        $this->assertSame($before - 6, (float) $material->refresh()->stock);

        $this->post(route('admin.warehouses.movements.store'), [
            'component_id' => $component->id, 'type' => 'keluar', 'qty' => 1, 'moved_at' => '2026-11-23',
        ])->assertRedirect();
        $this->assertSame(2.0, (float) $component->refresh()->stock);

        // Menghapus mutasi "masuk" mengembalikan bahan baku yang terpotong.
        $this->delete(route('admin.warehouses.movements.destroy', $component->movements()->where('type', 'masuk')->value('id')))->assertRedirect();
        $this->assertSame(-1.0, (float) $component->refresh()->stock);
        $this->assertSame($before, (float) $material->refresh()->stock);

        $this->delete(route('admin.components.destroy', $component))->assertSessionHas('error');
    }

    public function test_buying_finished_components_fills_the_semi_finished_warehouse(): void
    {
        $component = Component::where('code', 'KMP-TTB')->firstOrFail();
        $material = Material::query()->firstOrFail();
        $materialStock = (float) $material->stock;

        $this->get(route('admin.purchases.create', ['component' => $component->id]))->assertOk()->assertSee('Gudang Setengah Jadi');

        $this->post(route('admin.purchases.store'), [
            'destination' => 'setengah_jadi', 'component_id' => $component->id, 'material_id' => $material->id,
            'purchased_at' => '2026-11-22', 'qty' => 10, 'unit_price' => 15_000, 'supplier' => 'Toko Knalpot Jaya',
        ])->assertRedirect(route('admin.purchases.index'));

        $component->refresh();
        $this->assertSame(10.0, (float) $component->stock);
        $this->assertSame(15_000.0, (float) $component->cost_price);
        $this->assertSame(150_000.0, $component->stockValue());
        $this->assertSame($materialStock, (float) $material->refresh()->stock);

        $this->get(route('admin.purchases.index'))->assertOk()->assertSee('Tutup Silencer Belakang')->assertSee('KOMPONEN');

        // Mutasi dari pembelian hanya bisa dihapus lewat pembeliannya.
        $movement = $component->movements()->firstOrFail();
        $this->delete(route('admin.warehouses.movements.destroy', $movement))->assertSessionHas('error');

        $purchase = MaterialPurchase::latest('id')->firstOrFail();
        $this->delete(route('admin.purchases.destroy', $purchase))->assertRedirect();
        $this->assertSame(0.0, (float) $component->refresh()->stock);

        $this->post(route('admin.purchases.store'), [
            'destination' => 'setengah_jadi', 'purchased_at' => '2026-11-22', 'qty' => 1, 'unit_price' => 1,
        ])->assertSessionHasErrors('component_id');
    }

    public function test_warehouse_pages_show_the_three_warehouses(): void
    {
        $this->get(route('admin.warehouses.index'))->assertOk()
            ->assertSee('Gudang Bahan Baku')->assertSee('Gudang Setengah Jadi')->assertSee('Gudang Finish Good');

        $this->get(route('admin.warehouses.show', 'bahan-baku'))->assertOk()->assertSee(Material::where('is_active', true)->value('name'));
        $this->get(route('admin.warehouses.show', 'setengah-jadi'))->assertOk()->assertSee('Tabung Silencer');
        $this->get(route('admin.warehouses.show', 'finish-good'))->assertOk();
        $this->get(route('admin.warehouses.movements.create'))->assertOk();
        $this->get('/admin/gudang/lain')->assertNotFound();

        $this->put(route('admin.warehouses.update', 'finish-good'), ['name' => 'Gudang Knalpot Jadi', 'address' => 'Ruko depan'])
            ->assertRedirect(route('admin.warehouses.show', 'finish-good'));
        $this->get(route('admin.warehouses.index'))->assertSee('Gudang Knalpot Jadi');
    }
}
