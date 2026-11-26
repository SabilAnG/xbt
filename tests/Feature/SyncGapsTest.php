<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CostOption;
use App\Models\Formula;
use App\Models\Item;
use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Models\PipePiece;
use App\Models\Product;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockPhoto;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use App\Models\WorkerPieceEarning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Bagian yang sebelumnya belum tersinkron dengan sistem. */
class SyncGapsTest extends TestCase
{
    use RefreshDatabase;

    private Wallet $kas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        $this->post(route('admin.wallets.store'), ['name' => 'Kas', 'type' => 'cash', 'opening_balance' => 1_000_000, 'is_active' => 1]);
        $this->kas = Wallet::firstOrFail();
    }

    public function test_piecework_costs_become_worker_pay_and_labour_is_not_counted_twice(): void
    {
        $this->post(route('admin.cost-options.store'), ['name' => 'Las borongan', 'amount' => 10_000, 'payment_mode' => 'borongan'])->assertSessionHasNoErrors();
        $this->post(route('admin.cost-options.store'), ['name' => 'Finishing', 'amount' => 5_000, 'payment_mode' => 'gaji'])->assertSessionHasNoErrors();
        $this->assertTrue(CostOption::findByName('Las borongan')->isPiecework());

        $bolt = $this->unitMaterial('Baut L', 500);
        $this->post(route('admin.purchases.store'), ['purchased_at' => '2026-12-01', 'supplier' => 'Toko Baut', 'items' => [['material_id' => $bolt->id, 'qty' => 10, 'unit_price' => 500]]]);
        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'Matic', 'motor_type' => 'Mio', 'name' => 'Mio', 'is_active' => 1,
            'parts' => [['name' => 'Leheran', 'components' => [['name' => 'Baut', 'qty' => 1, 'materials' => [['material_id' => $bolt->id, 'qty' => 1]]]]]],
            'costs' => [['name' => 'Las borongan', 'amount' => 10_000], ['name' => 'Finishing', 'amount' => 5_000]],
        ])->assertSessionHasNoErrors();
        $formula = Formula::firstOrFail();

        // Ongkos borongan butuh penanggung jawab.
        $this->post(route('admin.productions.store'), ['produced_at' => '2026-12-02', 'formula_id' => $formula->id, 'qty' => 2, 'mode' => 'internal']);
        $job = Production::firstOrFail();
        $this->post(route('admin.productions.post', $job))->assertSessionHas('error');

        $this->post(route('admin.workers.store'), ['name' => 'Sidik', 'daily_wage' => 100_000, 'is_active' => 1]);
        $worker = Worker::firstOrFail();
        $this->put(route('admin.productions.update', $job), ['produced_at' => '2026-12-02', 'formula_id' => $formula->id, 'qty' => 2, 'mode' => 'internal', 'worker_id' => $worker->id]);
        $this->post(route('admin.productions.post', $job))->assertSessionMissing('error');

        $earning = WorkerPieceEarning::firstOrFail();
        $this->assertSame(1, WorkerPieceEarning::count(), 'Hanya ongkos borongan yang jadi upah');
        $this->assertEquals(20_000, (float) $earning->amount);
        $this->assertEquals(30_000, (float) $job->fresh()->other_cost);

        // Masuk slip gaji walau tanpa absensi; produksi tidak bisa dibatalkan selama sudah di slip.
        $this->post(route('admin.payrolls.store'), ['worker_id' => $worker->id, 'period_start' => '2026-11-30', 'period_end' => '2026-12-06'])->assertSessionHasNoErrors();
        $slip = WorkerPayroll::firstOrFail();
        $this->assertEquals(20_000, (float) $slip->piece_total);
        $this->assertEquals(20_000, (float) $slip->net_pay);
        $this->get(route('admin.payrolls.show', $slip))->assertOk()->assertSee('Borongan Las borongan');
        $this->post(route('admin.productions.cancel', $job))->assertSessionHas('error');
        $this->post(route('admin.payrolls.pay', $slip), ['paid_at' => '2026-12-06', 'wallet_id' => $this->kas->id])->assertSessionHasNoErrors();

        // Laba rugi: upah yang sudah ada di HPP tidak dihitung lagi sebagai biaya operasional.
        $this->get(route('admin.finance.index', ['dari' => '2026-12-01', 'sampai' => '2026-12-31']))->assertOk()
            ->assertViewHas('profit', fn (array $profit) => $profit['expenses'] == 20_000 && $profit['absorbed_labour'] == 30_000 && $profit['operating'] == -10_000);
    }

    public function test_stock_losses_appear_in_profit_and_loss(): void
    {
        $item = Item::create(['sku' => 'KNL-1', 'name' => 'Mio', 'cost_price' => 400_000, 'opening_stock' => 5, 'is_active' => true]);
        $item->recalculateStock();
        $this->post(route('admin.items.adjust', $item), ['adjusted_at' => '2026-12-03', 'actual_qty' => 3, 'reason' => 'hilang'])->assertSessionHasNoErrors();

        $this->get(route('admin.finance.index', ['dari' => '2026-12-01', 'sampai' => '2026-12-31']))->assertOk()
            ->assertSee('Selisih stok')
            ->assertViewHas('profit', fn (array $profit) => $profit['stock_result'] == -800_000 && $profit['net'] == -800_000);
    }

    public function test_sale_return_refund_goes_back_to_the_customer(): void
    {
        $item = Item::create(['sku' => 'KNL-1', 'name' => 'Mio', 'cost_price' => 400_000, 'opening_stock' => 1, 'is_active' => true]);
        $item->recalculateStock();
        $this->post(route('admin.sales.store'), ['sold_at' => '2026-12-01', 'lines' => [['item_id' => $item->id, 'qty' => -1, 'unit_price' => 600_000]]])->assertSessionHasNoErrors();
        $return = Sale::firstOrFail();
        $this->assertSame('UANG BELUM KEMBALI', $return->paymentStatus());

        $this->post(route('admin.sales.payments.store', $return), ['paid_at' => '2026-12-01', 'amount' => 100, 'wallet_id' => $this->kas->id])->assertSessionHas('error');
        $this->post(route('admin.sales.refunds.store', $return), ['paid_at' => '2026-12-01', 'amount' => 700_000, 'wallet_id' => $this->kas->id])->assertSessionHas('error');
        $this->post(route('admin.sales.refunds.store', $return), ['paid_at' => '2026-12-01', 'amount' => 600_000, 'wallet_id' => $this->kas->id])->assertSessionHasNoErrors();

        $this->assertSame('LUNAS', $return->fresh()->paymentStatus());
        $this->assertEquals(400_000, (float) $this->kas->fresh()->current_balance);
        $this->assertEquals(2, (float) $item->fresh()->stock);
    }

    public function test_activity_log_records_who_did_what(): void
    {
        $item = Item::create(['sku' => 'KNL-1', 'name' => 'Mio', 'cost_price' => 400_000, 'opening_stock' => 2, 'is_active' => true]);
        $item->recalculateStock();
        $this->post(route('admin.sales.store'), ['sold_at' => '2026-12-01', 'customer_name' => 'Budi', 'lines' => [['item_id' => $item->id, 'qty' => 1, 'unit_price' => 600_000]]]);
        $sale = Sale::firstOrFail();

        $log = ActivityLog::where('subject_type', Sale::class)->firstOrFail();
        $this->assertSame('created', $log->action);
        $this->assertSame(auth()->id(), $log->user_id);
        $this->get(route('admin.sales.show', $sale))->assertSee('Dicatat oleh');
        $this->get(route('admin.users.activity'))->assertOk()->assertSee('Penjualan '.$sale->invoice_number);

        // Hitung ulang otomatis (uang masuk) tidak tercatat sebagai "mengubah"; ubah pembeli tercatat.
        $this->put(route('admin.sales.update', $sale), ['customer_name' => 'Budi Santoso']);
        $this->assertSame(1, ActivityLog::where('subject_type', Sale::class)->where('action', 'updated')->count());

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.users.activity'))->assertForbidden();
    }

    public function test_bills_get_due_dates_from_supplier_terms(): void
    {
        $this->post(route('admin.suppliers.store'), ['name' => 'Toko Besi Jaya']);
        $supplier = Supplier::firstOrFail();
        $this->put(route('admin.suppliers.update', $supplier), ['name' => 'Toko Besi Jaya', 'payment_term_days' => 7, 'is_active' => 1])->assertSessionHasNoErrors();
        $bolt = $this->unitMaterial('Baut L', 500);

        $this->post(route('admin.purchases.store'), ['purchased_at' => today()->subDays(10)->toDateString(), 'supplier' => 'Toko Besi Jaya', 'items' => [['material_id' => $bolt->id, 'qty' => 10, 'unit_price' => 500]]])->assertSessionHasNoErrors();
        $bill = MaterialPurchase::firstOrFail();
        $this->assertTrue($bill->due_date->isSameDay(today()->subDays(3)));
        $this->assertTrue($bill->isOverdue());
        $this->get(route('admin.purchases.index', ['bayar' => 'jatuh-tempo']))->assertOk()->assertSee($bill->invoice_number);
        $this->get(route('admin.dashboard'))->assertSee('Hutang jatuh tempo');
    }

    public function test_dashboard_hides_finance_numbers_from_warehouse_role(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'gudang']));

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Stok knalpot jadi')->assertSee('Produksi draf')
            ->assertDontSee('Omzet')->assertDontSee('Laba kotor', false)->assertDontSee('+ Penjualan');
    }

    public function test_website_price_follows_product_online_price(): void
    {
        $item = Item::create(['sku' => 'KNL-1', 'name' => 'Mio Racing', 'sell_price_online' => 750_000, 'is_active' => true]);
        $this->post(route('admin.web-products.from-stock.store', $item));
        $product = Product::firstOrFail();
        $this->assertTrue($product->price_follows_item);
        $this->assertEquals(750_000, (float) $product->price);

        $item->update(['sell_price_online' => 800_000]);
        $this->assertEquals(800_000, (float) $product->fresh()->price);
    }

    public function test_pipe_bars_can_be_returned_and_counted_per_piece(): void
    {
        $raw = Warehouse::forType(Warehouse::RAW);
        $this->post(route('admin.materials.store'), ['name' => 'Pipa SS', 'warehouse_id' => $raw->id, 'measure' => 'length', 'bar_length_cm' => 600, 'unit' => 'Batang', 'is_active' => 1])->assertSessionHasNoErrors();
        $pipe = Material::firstOrFail();
        $this->post(route('admin.purchases.store'), ['purchased_at' => '2026-12-01', 'supplier' => 'Toko Pipa', 'items' => [['material_id' => $pipe->id, 'qty' => 3, 'bar_length_cm' => 600, 'unit_price' => 300_000]]])->assertSessionHasNoErrors();
        $purchase = MaterialPurchase::firstOrFail();
        $this->assertEquals(1_800, (float) $pipe->fresh()->stock);

        $this->post(route('admin.purchases.returns.store', $purchase), ['returned_at' => '2026-12-02', 'items' => [['material_purchase_item_id' => $purchase->items()->value('id'), 'qty' => 1]]])->assertSessionHasNoErrors();
        $this->assertEquals(1_200, (float) $pipe->fresh()->stock);
        $this->assertEquals(600_000, (float) $purchase->fresh()->total);
        $this->assertSame(1, PipePiece::where('status', PipePiece::RETURNED)->count());

        // Opname pipa: satu batang tidak ditemukan.
        $pieces = PipePiece::where('status', PipePiece::AVAILABLE)->pluck('id');
        $this->get(route('admin.stock-opname.index', ['gudang' => 'bahan-baku']))->assertOk()->assertSee('Pipa per batang');
        $this->post(route('admin.stock-opname.materials'), [
            'warehouse' => Warehouse::RAW, 'adjusted_at' => '2026-12-03', 'counted_by' => 'Rudi',
            'pieces_listed' => $pieces->all(), 'pieces_present' => [$pieces->first()],
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertEquals(600, (float) $pipe->fresh()->stock);

        // Batalkan retur: batangnya kembali.
        $this->delete(route('admin.purchases.returns.destroy', $purchase->returns()->firstOrFail()))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertEquals(1_200, (float) $pipe->fresh()->stock);
        $this->assertEquals(900_000, (float) $purchase->fresh()->total);
    }

    public function test_stock_opname_photos_are_kept_per_item_even_without_difference(): void
    {
        Storage::fake('site');
        $item = Item::create(['sku' => 'KNL-1', 'name' => 'Mio Racing', 'cost_price' => 400_000, 'opening_stock' => 4, 'is_active' => true]);
        $item->recalculateStock();
        $bolt = $this->unitMaterial('Baut L', 500);
        $this->post(route('admin.purchases.store'), ['purchased_at' => '2026-12-01', 'supplier' => 'Toko Baut', 'items' => [['material_id' => $bolt->id, 'qty' => 10, 'unit_price' => 500]]]);

        // Produk jadi: stok cocok, hanya foto → tetap tersimpan sebagai foto stok.
        $this->post(route('admin.stock-opname.items'), [
            'adjusted_at' => '2026-12-05', 'reason' => 'opname', 'counted_by' => 'Sidik', 'actual' => [$item->id => 4],
            'photos' => [$item->id => UploadedFile::fake()->image('rak.jpg', 800, 600)],
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $photo = StockPhoto::firstOrFail();
        $this->assertSame($item->id, $photo->subject_id);
        $this->assertSame('Sidik', $photo->counted_by);
        $this->assertEquals(4, (float) $photo->counted_qty);
        Storage::disk('site')->assertExists($photo->path);
        $this->get(route('admin.items.show', $item))->assertSee('Foto stok')->assertSee($photo->path);
        $this->get(route('admin.stock-opname.index'))->assertSee($photo->path);

        // Bahan: foto + selisih sekaligus.
        $this->post(route('admin.stock-opname.materials'), [
            'warehouse' => Warehouse::ACCESSORY, 'adjusted_at' => '2026-12-05', 'counted_by' => 'Rudi', 'actual' => [$bolt->id => 9],
            'photos' => [$bolt->id => UploadedFile::fake()->image('baut.jpg')],
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame(1, $bolt->stockPhotos()->count());
        $this->get(route('admin.materials.show', $bolt))->assertSee('Foto stok');

        // Bukan gambar → ditolak.
        $this->post(route('admin.stock-opname.items'), [
            'adjusted_at' => '2026-12-05', 'reason' => 'opname', 'counted_by' => 'Sidik',
            'photos' => [$item->id => UploadedFile::fake()->create('catatan.pdf', 10, 'application/pdf')],
        ])->assertSessionHasErrors('photos.'.$item->id);
    }

    private function unitMaterial(string $name, float $price): Material
    {
        $this->post(route('admin.materials.store'), ['name' => $name, 'warehouse_id' => Warehouse::forType(Warehouse::ACCESSORY)->id, 'measure' => 'unit', 'unit' => 'Pcs', 'last_price' => $price, 'is_active' => 1]);

        return Material::where('name', $name)->firstOrFail();
    }
}
