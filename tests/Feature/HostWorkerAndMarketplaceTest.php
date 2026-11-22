<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseType;
use App\Models\Host;
use App\Models\HostPayroll;
use App\Models\Item;
use App\Models\LiveSession;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use Database\Seeders\KnalpotWorkbookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostWorkerAndMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_live_hours_follow_the_workbook_formula(): void
    {
        $host = Host::create(['name' => 'ANGGI', 'hourly_rate' => 15_000]);

        $overnight = LiveSession::create(['date' => '2026-11-16', 'host_id' => $host->id, 'start_time' => '22:30', 'end_time' => '01:00']);
        $manual = LiveSession::create(['date' => '2026-11-17', 'host_id' => $host->id, 'start_time' => '19:00', 'end_time' => '21:00', 'manual_hours' => 3]);

        $this->assertEquals(2.5, $overnight->totalHours());
        $this->assertEquals(3, $manual->totalHours());

        $payroll = HostPayroll::create(['period_start' => '2026-11-15', 'period_end' => '2026-11-21', 'host_id' => $host->id]);
        $this->assertEquals(5.5, $payroll->totalHours());
        $this->assertEquals(82_500, $payroll->amountDue());
    }

    public function test_attendance_drives_worker_pay_and_paid_payroll_can_be_recorded_as_expense(): void
    {
        $type = ExpenseType::create(['name' => 'Usaha', 'slug' => 'usaha']);
        ExpenseCategory::create(['expense_type_id' => $type->id, 'name' => 'Gaji Tukang', 'slug' => 'gaji-tukang']);
        $worker = Worker::create(['name' => 'SIDIK', 'daily_wage' => 150_000]);

        $this->post(route('admin.attendance.store'), ['date' => '2026-11-16', 'values' => [$worker->id => '1']])->assertRedirect();
        $this->post(route('admin.attendance.store'), ['date' => '2026-11-17', 'values' => [$worker->id => '0.5']])->assertRedirect();

        $this->post(route('admin.payrolls.store', 'tukang'), [
            'period_start' => '2026-11-16', 'period_end' => '2026-11-21', 'people' => [$worker->id],
            'bonus' => 50_000, 'deduction' => 20_000, 'status' => 'Sudah', 'paid_at' => '2026-11-21', 'record_expense' => 1,
        ])->assertRedirect(route('admin.payrolls.index', 'tukang'));

        $payroll = WorkerPayroll::firstOrFail();
        $this->assertEquals(1.5, $payroll->daysPresent());
        $this->assertEquals(255_000, $payroll->amountDue());
        $this->assertEquals(255_000, (float) Expense::sole()->amount);

        foreach (['admin.live-sessions.index', 'admin.attendance.index', 'admin.hosts.index', 'admin.list-options.index', 'admin.procurement.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.payrolls.index', 'tukang'))->assertOk()->assertSee('SIDIK');
        $this->get(route('admin.payrolls.index', 'host'))->assertOk();
    }

    public function test_stock_item_can_be_uploaded_to_the_website_with_its_own_price(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);
        $item = Item::where('sku', 'KNL-002')->firstOrFail();

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
