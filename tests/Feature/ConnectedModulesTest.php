<?php

namespace Tests\Feature;

use App\Models\Formula;
use App\Models\Item;
use App\Models\ItemAdjustment;
use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Models\Order;
use App\Models\Production;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SupplierDeposit;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use App\Services\DashboardSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Modul yang disambungkan: pekerja ↔ produksi, pesanan ↔ penjualan, hutang vendor, piutang, opname, retur, hak akses. */
class ConnectedModulesTest extends TestCase
{
    use RefreshDatabase;

    private Wallet $kas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        $this->post(route('admin.wallets.store'), ['name' => 'Kas', 'type' => 'cash', 'opening_balance' => 2_000_000, 'is_active' => 1]);
        $this->kas = Wallet::firstOrFail();
    }

    public function test_production_person_in_charge_comes_from_workers(): void
    {
        $this->post(route('admin.workers.store'), ['name' => 'Sidik', 'daily_wage' => 100_000, 'is_active' => 1]);
        $worker = Worker::firstOrFail();
        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'Matic', 'motor_type' => 'Mio', 'name' => 'Mio', 'is_active' => 1,
            'parts' => [['name' => 'Leheran', 'components' => [['name' => 'P1', 'qty' => 1, 'materials' => []]]]],
        ])->assertSessionHasNoErrors();

        $this->get(route('admin.productions.create'))->assertOk()->assertSee('Sidik');
        $this->post(route('admin.productions.store'), [
            'produced_at' => '2026-12-01', 'formula_id' => Formula::firstOrFail()->id, 'qty' => 5, 'mode' => 'internal', 'worker_id' => $worker->id,
        ])->assertSessionHasNoErrors();

        $job = Production::firstOrFail();
        $this->assertSame($worker->id, $job->worker_id);
        $this->assertSame('Sidik', $job->worker);
        $this->get(route('admin.workers.show', $worker))->assertOk()->assertSee($job->production_number);
        $this->get(route('admin.productions.index', ['pekerja' => $worker->id]))->assertOk()->assertSee($job->production_number);
    }

    public function test_multi_product_sale_with_installments_and_tracking_order(): void
    {
        $mio = $this->product('Mio Racing', 5, 400_000);
        $beat = $this->product('Beat Racing', 2, 350_000);

        // Stok dihitung per produk untuk semua baris sekaligus.
        $this->post(route('admin.sales.store'), ['sold_at' => '2026-12-01', 'lines' => [
            ['item_id' => $beat->id, 'qty' => 2, 'unit_price' => 600_000],
            ['item_id' => $beat->id, 'qty' => 1, 'unit_price' => 600_000],
        ]])->assertSessionHasErrors('lines');
        $this->assertSame(0, Sale::count());

        $this->post(route('admin.sales.store'), [
            'sold_at' => '2026-12-01', 'channel' => 'Website', 'customer_name' => 'Budi', 'customer_phone' => '0812',
            'lines' => [
                ['item_id' => $mio->id, 'qty' => 2, 'unit_price' => 700_000],
                ['item_id' => $beat->id, 'qty' => 1, 'unit_price' => 600_000],
            ],
            'discount' => 100_000, 'paid_amount' => 900_000, 'wallet_id' => $this->kas->id,
        ])->assertSessionHasNoErrors();

        $sale = Sale::firstOrFail();
        $this->assertEquals(1_900_000, (float) $sale->total);
        $this->assertEquals(2 * 400_000 + 350_000, (float) $sale->total_cost);
        $this->assertEquals(3, (float) $mio->fresh()->stock);
        $this->assertEquals(1, (float) $beat->fresh()->stock);
        $this->assertEquals(1_000_000, $sale->fresh()->receivable());

        // Cicilan: tidak boleh melebihi sisa piutang; dua cicilan melunasi.
        $this->post(route('admin.sales.payments.store', $sale), ['paid_at' => '2026-12-05', 'amount' => 1_500_000, 'wallet_id' => $this->kas->id])->assertSessionHas('error');
        $this->post(route('admin.sales.payments.store', $sale), ['paid_at' => '2026-12-05', 'amount' => 400_000, 'wallet_id' => $this->kas->id])->assertSessionHasNoErrors();
        $this->post(route('admin.sales.payments.store', $sale), ['paid_at' => '2026-12-10', 'amount' => 600_000, 'wallet_id' => $this->kas->id])->assertSessionHasNoErrors();
        $this->assertTrue($sale->fresh()->isPaidOff());
        $this->assertEquals(3_900_000, (float) $this->kas->fresh()->current_balance);
        $this->get(route('admin.sales.show', $sale))->assertOk()->assertSee('Mio Racing')->assertSee('Beat Racing')->assertSee('LUNAS');

        // Uang masuk di laporan dihitung per tanggal cicilan.
        $this->get(route('admin.finance.index', ['dari' => '2026-12-05', 'sampai' => '2026-12-31']))->assertOk()
            ->assertViewHas('cash', fn (array $cash) => $cash['sales_in'] == 1_000_000);

        // Resi/tracking dibuat dari nota: nomor & pembeli terisi, tidak bisa dobel.
        $this->get(route('admin.orders.create', ['penjualan' => $sale->id]))->assertOk()->assertSee($sale->invoice_number)->assertSee('Budi');
        $this->post(route('admin.orders.store'), ['sale_id' => $sale->id, 'order_number' => $sale->invoice_number, 'status' => 'processing', 'customer_name' => 'Budi'])->assertSessionHasNoErrors();
        $this->post(route('admin.orders.store'), ['sale_id' => $sale->id, 'order_number' => 'LAIN-1', 'status' => 'processing'])->assertSessionHasErrors('sale_id');
        $this->assertSame($sale->id, Order::firstOrFail()->sale_id);
        $this->get(route('admin.orders.create', ['penjualan' => $sale->id]))->assertRedirect(route('admin.orders.edit', Order::firstOrFail()));

        // Hapus nota: stok & semua uang masuk kembali.
        $this->delete(route('admin.sales.destroy', $sale))->assertRedirect();
        $this->assertEquals(5, (float) $mio->fresh()->stock);
        $this->assertEquals(2_000_000, (float) $this->kas->fresh()->current_balance);
        $this->assertNull(Order::firstOrFail()->sale_id);
    }

    public function test_vendor_bills_paid_together_using_deposit_first(): void
    {
        $this->post(route('admin.suppliers.store'), ['name' => 'Bengkel Bending Jaya']);
        $vendor = Supplier::firstOrFail();
        $first = $this->bill($vendor, '2026-12-01', 30_000);
        $second = $this->bill($vendor, '2026-12-02', 40_000);

        $this->post(route('admin.suppliers.deposits.store', $vendor), ['paid_at' => '2026-11-28', 'amount' => 50_000, 'wallet_id' => $this->kas->id])->assertSessionHasNoErrors();
        $this->assertEquals(50_000, $vendor->depositBalance());
        $this->assertEquals(1_950_000, (float) $this->kas->fresh()->current_balance);

        // Dari dompet melebihi sisa setelah DP → ditolak.
        $this->post(route('admin.suppliers.pay', $vendor), ['paid_at' => '2026-12-05', 'purchases' => [$first->id, $second->id], 'use_deposit' => 1, 'amount' => 30_000, 'wallet_id' => $this->kas->id])
            ->assertSessionHas('error');

        $this->post(route('admin.suppliers.pay', $vendor), ['paid_at' => '2026-12-05', 'purchases' => [$first->id, $second->id], 'use_deposit' => 1, 'amount' => 20_000, 'wallet_id' => $this->kas->id])
            ->assertSessionHasNoErrors();

        // DP 50.000 melunasi tagihan terlama (30.000) + 20.000 tagihan kedua; dompet 20.000.
        $this->assertSame(0.0, $first->fresh()->payable());
        $this->assertSame(0.0, $second->fresh()->payable());
        $this->assertEquals(0, $vendor->depositBalance());
        $this->assertEquals(1_930_000, (float) $this->kas->fresh()->current_balance);
        $this->get(route('admin.suppliers.show', $vendor))->assertOk()->assertSee('Uang muka');

        // DP yang sudah terpakai tidak bisa dihapus; uang keluar tidak dihitung dua kali.
        $this->delete(route('admin.suppliers.deposits.destroy', SupplierDeposit::firstOrFail()))->assertSessionHas('error');
        $this->assertEquals(70_000, app(DashboardSummary::class)->headline()['expenses']);
    }

    public function test_finished_goods_stock_opname(): void
    {
        $item = $this->product('Mio Racing', 10, 400_000);

        $this->post(route('admin.items.adjust', $item), ['adjusted_at' => '2026-12-01', 'actual_qty' => 8, 'reason' => 'rusak', 'notes' => 'penyok'])->assertSessionHasNoErrors();
        $this->assertEquals(8, (float) $item->fresh()->stock);
        $adjustment = ItemAdjustment::firstOrFail();
        $this->assertEquals(-2, (float) $adjustment->difference);
        $this->assertEquals(-800_000, $adjustment->value());
        $this->get(route('admin.items.show', $item))->assertOk()->assertSee($adjustment->adjustment_number);

        $this->post(route('admin.items.adjust', $item), ['adjusted_at' => '2026-12-01', 'actual_qty' => 8, 'reason' => 'opname'])->assertSessionHas('error');

        $this->delete(route('admin.items.adjustments.destroy', $adjustment))->assertSessionHasNoErrors();
        $this->assertEquals(10, (float) $item->fresh()->stock);
    }

    public function test_stock_opname_menu_adjusts_many_products_and_materials_at_once(): void
    {
        $mio = $this->product('Mio Racing', 10, 400_000);
        $beat = $this->product('Beat Racing', 4, 350_000);
        $accessory = Warehouse::forType(Warehouse::ACCESSORY);
        $this->post(route('admin.materials.store'), ['name' => 'Baut L', 'warehouse_id' => $accessory->id, 'measure' => 'unit', 'unit' => 'Pcs', 'is_active' => 1]);
        $bolt = Material::firstOrFail();
        $this->post(route('admin.purchases.store'), ['purchased_at' => '2026-12-01', 'supplier' => 'Toko Baut', 'items' => [['material_id' => $bolt->id, 'qty' => 50, 'unit_price' => 500]]]);

        $this->get(route('admin.stock-opname.index'))->assertOk()->assertSee('Mio Racing')->assertSee('Beat Racing');
        $this->get(route('admin.stock-opname.index', ['gudang' => 'aksesoris']))->assertOk()->assertSee('Baut L');

        // Beat dikosongkan (tidak diubah), Mio sama dengan sistem → tidak ada perubahan.
        $this->post(route('admin.stock-opname.items'), ['adjusted_at' => '2026-12-05', 'reason' => 'opname', 'actual' => [$mio->id => 10, $beat->id => null]])->assertSessionHas('error');

        $this->post(route('admin.stock-opname.items'), ['adjusted_at' => '2026-12-05', 'reason' => 'opname', 'actual' => [$mio->id => 9, $beat->id => 5]])->assertSessionHasNoErrors();
        $this->assertEquals(9, (float) $mio->fresh()->stock);
        $this->assertEquals(5, (float) $beat->fresh()->stock);
        $this->assertSame(2, ItemAdjustment::count());

        $this->post(route('admin.stock-opname.materials'), ['warehouse' => 'aksesoris', 'adjusted_at' => '2026-12-05', 'actual' => [$bolt->id => 47]])->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertEquals(47, $bolt->fresh()->load('stocks')->stockIn($accessory));
    }

    public function test_purchase_return_takes_stock_out_and_refunds_overpayment(): void
    {
        $this->post(route('admin.materials.store'), ['name' => 'Baut L', 'warehouse_id' => Warehouse::forType(Warehouse::ACCESSORY)->id, 'measure' => 'unit', 'unit' => 'Pcs', 'is_active' => 1]);
        $bolt = Material::firstOrFail();
        $this->post(route('admin.purchases.store'), [
            'purchased_at' => '2026-12-01', 'supplier' => 'Toko Baut',
            'items' => [['material_id' => $bolt->id, 'qty' => 10, 'unit_price' => 1_000]],
            'paid_amount' => 10_000, 'wallet_id' => $this->kas->id,
        ])->assertSessionHasNoErrors();
        $purchase = MaterialPurchase::firstOrFail();
        $line = $purchase->items()->firstOrFail();

        // Sudah lunas: retur tanpa uang kembali ditolak, lebih dari yang diterima ditolak.
        $this->post(route('admin.purchases.returns.store', $purchase), ['returned_at' => '2026-12-02', 'items' => [['material_purchase_item_id' => $line->id, 'qty' => 3]]])->assertSessionHas('error');
        $this->post(route('admin.purchases.returns.store', $purchase), ['returned_at' => '2026-12-02', 'items' => [['material_purchase_item_id' => $line->id, 'qty' => 11]], 'refund_amount' => 11_000, 'wallet_id' => $this->kas->id])->assertSessionHas('error');
        $this->assertEquals(10, (float) $bolt->fresh()->stock);

        $this->post(route('admin.purchases.returns.store', $purchase), [
            'returned_at' => '2026-12-02', 'items' => [['material_purchase_item_id' => $line->id, 'qty' => 3]], 'refund_amount' => 3_000, 'wallet_id' => $this->kas->id, 'notes' => 'karatan',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(7, (float) $bolt->fresh()->stock);
        $this->assertEquals(7_000, (float) $purchase->fresh()->total);
        $this->assertEquals(7_000, (float) $purchase->fresh()->paid_amount);
        $this->assertEquals(2_000_000 - 10_000 + 3_000, (float) $this->kas->fresh()->current_balance);
        $this->get(route('admin.purchases.show', $purchase))->assertOk()->assertSee('karatan')->assertSee('Retur ke supplier');

        $this->delete(route('admin.purchases.returns.destroy', $purchase->returns()->firstOrFail()))->assertSessionHasNoErrors();
        $this->assertEquals(10, (float) $bolt->fresh()->stock);
        $this->assertEquals(10_000, (float) $purchase->fresh()->total);
        $this->assertEquals(1_990_000, (float) $this->kas->fresh()->current_balance);
    }

    public function test_roles_limit_menus_and_pages(): void
    {
        $this->post(route('admin.users.store'), ['name' => 'Kasir', 'email' => 'kasir@xbt.test', 'role' => 'kasir', 'password' => 'rahasia123'])->assertSessionHasNoErrors();
        $this->post(route('admin.users.store'), ['name' => 'Gudang', 'email' => 'gudang@xbt.test', 'role' => 'gudang', 'password' => 'rahasia123'])->assertSessionHasNoErrors();
        $cashier = User::where('role', 'kasir')->firstOrFail();
        $keeper = User::where('role', 'gudang')->firstOrFail();

        $this->actingAs($cashier);
        $this->get(route('admin.sales.index'))->assertOk();
        $this->get(route('admin.warehouses.show', 'finish-good'))->assertOk();
        $this->get(route('admin.purchases.index'))->assertForbidden();
        $this->get(route('admin.warehouses.show', 'bahan-baku'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('Formula')->assertSee('Penjualan');

        $this->actingAs($keeper);
        $this->get(route('admin.productions.index'))->assertOk();
        $this->get(route('admin.reports.production'))->assertOk();
        $this->get(route('admin.finance.index'))->assertForbidden();
        $this->get(route('admin.sales.create'))->assertForbidden();

        // Akun nonaktif dikeluarkan.
        $keeper->update(['is_active' => false]);
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        // Pemilik terakhir tidak bisa diturunkan perannya.
        $owner = User::where('role', 'owner')->firstOrFail();
        $this->actingAs($owner);
        $this->put(route('admin.users.update', $owner), ['name' => $owner->name, 'email' => $owner->email, 'role' => 'admin', 'is_active' => 1])->assertSessionHas('error');
        $this->assertTrue($owner->fresh()->isOwner());
    }

    public function test_worker_advance_counts_as_money_out_when_given_not_again_at_payroll(): void
    {
        $this->post(route('admin.workers.store'), ['name' => 'Sidik', 'daily_wage' => 100_000, 'is_active' => 1]);
        $worker = Worker::firstOrFail();
        $this->post(route('admin.workers.advances.store', $worker), ['given_at' => '2026-11-30', 'amount' => 50_000, 'wallet_id' => $this->kas->id])->assertSessionHasNoErrors();
        $this->assertEquals(50_000, app(DashboardSummary::class)->headline()['expenses']);

        $this->post(route('admin.attendances.store'), ['worked_on' => '2026-12-01', 'rows' => [$worker->id => ['status' => 'hadir']]]);
        $this->post(route('admin.payrolls.store'), ['worker_id' => $worker->id, 'period_start' => '2026-11-30', 'period_end' => '2026-12-06']);
        $this->post(route('admin.payrolls.pay', WorkerPayroll::firstOrFail()), ['paid_at' => '2026-12-06', 'wallet_id' => $this->kas->id])->assertSessionHasNoErrors();

        // Upah 100.000 = kasbon 50.000 (sudah keluar) + transfer 50.000.
        $this->assertEquals(100_000, app(DashboardSummary::class)->headline()['expenses']);
        $this->assertEquals(1_900_000, (float) $this->kas->fresh()->current_balance);
    }

    private function product(string $name, float $stock, float $cost): Item
    {
        $item = Item::create(['sku' => strtoupper(substr($name, 0, 3)).'-'.random_int(100, 999), 'name' => $name, 'cost_price' => $cost, 'opening_stock' => $stock, 'is_active' => true]);
        $item->recalculateStock();

        return $item->fresh();
    }

    private function bill(Supplier $vendor, string $date, float $total): MaterialPurchase
    {
        return MaterialPurchase::create([
            'invoice_number' => 'JS-'.$date, 'kind' => 'jasa', 'purchased_at' => $date, 'supplier_id' => $vendor->id,
            'supplier' => $vendor->name, 'total' => $total, 'paid_amount' => 0,
        ]);
    }
}
