<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_stock_item_can_be_uploaded_to_the_website_with_its_own_price(): void
    {
        $item = Item::create([
            'sku' => 'KNL-002', 'name' => 'Mio Standar Racing XBT', 'model_motor' => 'Mio',
            'cost_price' => 472_000, 'sell_price_online' => 890_000, 'opening_stock' => 3,
        ]);
        $item->recalculateStock();

        $this->get(route('admin.web-products.from-stock'))->assertOk()->assertSee($item->name)->assertSee('Rp 472.000');

        $this->post(route('admin.web-products.from-stock.store', $item))->assertRedirect();
        $product = Product::where('item_id', $item->id)->firstOrFail();

        $this->assertSame('IDR', $product->currency);
        $this->assertEquals(890_000, (float) $product->price);
        $this->assertFalse($product->is_active);

        // Admin melihat harga modal & harga jual online, lalu mengatur harga website.
        $this->get(route('admin.web-products.edit', $product))->assertOk()
            ->assertSee('Harga modal (HPP)')->assertSee('Rp 472.000')->assertSee('Rp 890.000');

        $this->put(route('admin.web-products.update', $product), [
            'name' => $product->name, 'slug' => $product->slug, 'price' => 850_000, 'currency' => 'IDR',
            'item_id' => $item->id, 'sort_order' => 1, 'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Website menampilkan harga website saja, bukan harga modal.
        $this->get(route('products.index'))->assertOk()->assertSee('Rp 850.000')->assertSee('Pesan via WhatsApp')->assertDontSee('Rp 472.000');
        $this->get(route('products.show', $product->slug))->assertOk()->assertSee('Stok tersedia');
    }

    public function test_whatsapp_order_uses_the_number_from_site_settings(): void
    {
        Setting::put('whatsapp.sales1', '6281111111111');
        $product = Product::create(['name' => 'Knalpot Mio FD', 'slug' => 'knalpot-mio-fd', 'price' => 850_000, 'currency' => 'IDR']);

        $response = $this->get(route('redirect.product-checkout', ['product' => $product->slug, 'customer_support' => 1]));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/6281111111111?text=', $response->headers->get('Location'));
        $this->assertStringContainsString(rawurlencode('Rp 850.000'), $response->headers->get('Location'));
    }
}
