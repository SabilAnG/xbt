<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebsiteAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('site');
        $this->seed([SiteContentSeeder::class, ProductSeeder::class]);
        $this->actingAs(User::factory()->create());
    }

    public function test_website_admin_pages_render(): void
    {
        $order = Order::create(['order_number' => 'HS-1']);
        $order->shipments()->create(['tracking_number' => 'JNE1', 'status' => 'in_transit'])
            ->events()->create(['status' => 'Diterima kurir', 'happened_at' => now()]);

        $this->get(route('admin.site-settings.edit'))->assertOk()->assertSee('Hypersonic Speed Tech');
        $this->get(route('admin.content-blocks.index', ['page' => 'home']))->assertOk();
        $this->get(route('admin.content-blocks.edit', ContentBlock::where('type', 'image')->first()))->assertOk();
        $this->get(route('admin.content-blocks.edit', ContentBlock::where('type', 'text')->first()))->assertOk();
        $this->get(route('admin.web-products.index'))->assertOk();
        $this->get(route('admin.web-products.create'))->assertOk();
        $this->get(route('admin.web-products.edit', Product::first()))->assertOk();
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('HS-1');
        $this->get(route('admin.orders.edit', $order))->assertOk()->assertSee('Diterima kurir');
    }

    public function test_settings_save_text_and_uploaded_logo(): void
    {
        $this->put(route('admin.site-settings.update'), [
            'site_name' => 'XBT Knalpot',
            'whatsapp_primary' => '6281111111111',
            'site_logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('XBT Knalpot', Setting::get('site.name'));
        $this->assertSame('6281111111111', Setting::get('whatsapp.primary'));
        $this->assertStringStartsWith('uploads/site/', Setting::get('site.logo'));
        Storage::disk('site')->assertExists(Setting::get('site.logo'));

        $this->put(route('admin.site-settings.update'), ['site_name' => 'X', 'whatsapp_primary' => '+62 811'])
            ->assertSessionHasErrors('whatsapp_primary');
    }

    public function test_editing_a_content_block_changes_the_public_page(): void
    {
        $block = ContentBlock::where('type', 'text')->where('page', 'home')->firstOrFail();

        $this->put(route('admin.content-blocks.update', $block), ['label' => $block->label, 'value' => 'Teks baru dari admin'])
            ->assertRedirect();

        $this->assertSame('Teks baru dari admin', ContentBlock::get($block->key));
        $this->get('/')->assertSee('Teks baru dari admin');
    }

    public function test_product_with_gallery_can_be_created_and_images_removed(): void
    {
        $this->post(route('admin.web-products.store'), [
            'name' => 'Knalpot XBT Mio',
            'price' => 890000,
            'currency' => 'IDR',
            'sort_order' => 9,
            'is_active' => 1,
            'new_images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::where('slug', 'knalpot-xbt-mio')->firstOrFail();
        $this->assertCount(2, $product->images);
        $this->get(route('products.show', $product->slug))->assertOk()->assertSee('Knalpot XBT Mio');

        $first = $product->images->first();
        $this->put(route('admin.web-products.update', $product), [
            'name' => 'Knalpot XBT Mio',
            'slug' => $product->slug,
            'price' => 900000,
            'currency' => 'IDR',
            'sort_order' => 9,
            'images' => [$first->id => ['delete' => 1]],
        ])->assertRedirect();

        $this->assertCount(1, $product->fresh()->images);
        Storage::disk('site')->assertMissing($first->path);
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_product_gallery_accepts_videos_and_shows_them_on_the_website(): void
    {
        $this->post(route('admin.web-products.store'), [
            'name' => 'Knalpot Video',
            'price' => 500000,
            'currency' => 'IDR',
            'sort_order' => 1,
            'is_active' => 1,
            'new_media' => [
                UploadedFile::fake()->create('demo.mp4', 2048, 'video/mp4'),
                UploadedFile::fake()->image('foto.jpg'),
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::where('slug', 'knalpot-video')->firstOrFail();
        [$video, $photo] = $product->images->all();

        $this->assertTrue($video->isVideo());
        $this->assertFalse($photo->isVideo());
        Storage::disk('site')->assertExists($video->path);

        // Kartu & keranjang memakai foto, bukan video; halaman produk memutar videonya.
        $this->assertTrue($product->cardImage()->is($photo));
        $this->get(route('products.show', $product->slug))->assertOk()->assertSee('<video', false)->assertSee($video->path);
        $this->get(route('admin.web-products.edit', $product))->assertOk()->assertSee('VIDEO');
    }

    public function test_preview_renders_the_real_product_page_from_unsaved_input(): void
    {
        $product = Product::create(['name' => 'Knalpot Lama', 'slug' => 'knalpot-lama', 'price' => 100000, 'currency' => 'IDR', 'sort_order' => 1, 'is_active' => false]);
        $photo = $product->images()->create(['path' => 'uploads/products/a.jpg', 'sort_order' => 0]);

        $this->post(route('admin.web-products.preview'), [
            'product_id' => $product->id,
            'name' => 'Knalpot Baru Belum Disimpan',
            'price' => 750000,
            'currency' => 'IDR',
            'description' => '<p>Deskripsi pratinjau</p>',
            'images' => [$photo->id => ['delete' => 1]],
            'preview_new' => ['video'],
        ])->assertOk()
            ->assertSee('Knalpot Baru Belum Disimpan')
            ->assertSee('Deskripsi pratinjau', false)
            ->assertSee('__pending__/0', false)
            ->assertDontSee('uploads/products/a.jpg');

        // Pratinjau tidak menyimpan apa pun.
        $this->assertSame('Knalpot Lama', $product->fresh()->name);
        $this->assertCount(1, $product->fresh()->images);
    }

    public function test_draft_is_hidden_until_posted_to_the_website(): void
    {
        $base = ['name' => 'Knalpot Draf', 'price' => 500000, 'currency' => 'IDR', 'sort_order' => 1];

        $this->post(route('admin.web-products.store'), $base + ['publish' => 0])->assertRedirect();
        $product = Product::where('slug', 'knalpot-draf')->firstOrFail();
        $this->assertFalse($product->is_active);
        $this->get(route('products.show', $product->slug))->assertNotFound();

        $this->put(route('admin.web-products.update', $product), $base + ['slug' => $product->slug, 'publish' => 1])
            ->assertSessionHas('status', 'Disimpan — tayang di website.');
        $this->get(route('products.show', $product->slug))->assertOk();

        $this->get(route('admin.web-products.edit', $product))->assertOk()->assertSee('Tarik jadi draf')->assertSee('product-preview');
    }

    public function test_product_gallery_rejects_unsupported_and_oversized_files(): void
    {
        $base = ['name' => 'Knalpot Tolak', 'price' => 1, 'currency' => 'IDR', 'sort_order' => 1];

        $this->post(route('admin.web-products.store'), $base + ['new_media' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]])
            ->assertSessionHasErrors('new_media.0');
        $this->post(route('admin.web-products.store'), $base + ['new_media' => [UploadedFile::fake()->create('besar.mp4', 51 * 1024, 'video/mp4')]])
            ->assertSessionHasErrors('new_media.0');

        $this->assertDatabaseMissing('products', ['name' => 'Knalpot Tolak']);
    }

    public function test_order_with_shipment_and_event_answers_the_tracking_endpoint(): void
    {
        $this->post(route('admin.orders.store'), ['order_number' => 'HS-777', 'status' => 'shipped'])->assertRedirect();
        $order = Order::where('order_number', 'HS-777')->firstOrFail();

        $this->post(route('admin.orders.shipments.store', $order), ['tracking_number' => 'JNE123', 'provider' => 'JNE', 'status' => 'in_transit'])
            ->assertRedirect(route('admin.orders.edit', $order));
        $shipment = $order->shipments()->firstOrFail();

        $this->post(route('admin.shipments.events.store', $shipment), ['status' => 'Paket dikirim', 'happened_at' => '2026-11-20 10:00', 'location' => 'Bekasi'])
            ->assertRedirect();

        $this->postJson(route('tracking.track'), ['order_number' => 'HS-777'])
            ->assertOk()
            ->assertSee('JNE123')
            ->assertSee('Paket dikirim');
    }
}
