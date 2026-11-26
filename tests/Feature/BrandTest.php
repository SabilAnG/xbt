<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Item;
use App\Models\User;
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
        Brand::create(['name' => 'FD', 'code' => 'FD', 'sort_order' => 1]);
        Brand::create(['name' => 'XBT', 'code' => 'XBT', 'sort_order' => 2]);
    }

    public function test_brands_are_listed_and_shown_on_the_dashboard(): void
    {
        $this->assertSame(['FD', 'XBT'], Brand::names()->all());

        $this->get(route('admin.brands.index'))->assertOk()->assertSee('XBT')->assertSee('FD');
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Ringkasan per merek');
    }

    public function test_products_only_accept_registered_brands(): void
    {
        $this->post(route('admin.brands.store'), ['name' => 'ABC', 'code' => 'ABC', 'color' => '#2563eb', 'is_active' => 1])
            ->assertRedirect(route('admin.brands.index'));

        $product = ['sku' => 'X1', 'model_motor' => 'Mio', 'cost_price' => 1, 'sell_price' => 1, 'sell_price_online' => 1, 'min_stock' => 0];

        $this->post(route('admin.items.store'), $product + ['brand' => 'TIDAKADA'])->assertSessionHasErrors('brand');
        $this->post(route('admin.items.store'), $product + ['brand' => 'ABC'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('items', ['sku' => 'X1', 'brand' => 'ABC']);
    }

    public function test_renaming_a_brand_updates_products_and_a_brand_in_use_cannot_be_deleted(): void
    {
        $brand = Brand::where('name', 'XBT')->firstOrFail();
        Item::create(['sku' => 'KNL-1', 'name' => 'Mio XBT', 'brand' => 'XBT']);

        $this->put(route('admin.brands.update', $brand), ['name' => 'XBT Racing', 'code' => 'XBT', 'color' => '#ea580c', 'is_active' => 1])
            ->assertRedirect(route('admin.brands.index'));

        $this->assertDatabaseHas('items', ['sku' => 'KNL-1', 'brand' => 'XBT Racing']);

        $this->delete(route('admin.brands.destroy', $brand))->assertSessionHas('error');
        $this->assertDatabaseHas('brands', ['name' => 'XBT Racing']);
    }
}
