<?php

namespace Tests\Feature;

use App\Models\Formula;
use App\Models\Item;
use App\Models\ItemAdjustment;
use App\Models\Material;
use App\Models\MaterialAdjustment;
use App\Models\MaterialPurchase;
use App\Models\Order;
use App\Models\PipePiece;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\PurchasePayment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorService;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use App\Services\DashboardSummary;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Satu siklus usaha lengkap memakai semua modul yang saling tersambung,
 * lalu dicek: angka saling cocok, dan setiap halaman terbuka (atau ditolak
 * dengan benar) untuk setiap peran.
 */
class FullBusinessCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'owner']));
    }

    public function test_full_cycle_from_purchase_to_payroll_stays_consistent_for_every_role(): void
    {
        $raw = Warehouse::forType(Warehouse::RAW);
        $accessory = Warehouse::forType(Warehouse::ACCESSORY);
        $offcut = Warehouse::forType(Warehouse::OFFCUT);

        // Dompet & master.
        $this->post(route('admin.wallets.store'), ['name' => 'Kas', 'type' => 'cash', 'opening_balance' => 10_000_000, 'is_active' => 1]);
        $this->post(route('admin.wallets.store'), ['name' => 'BCA', 'type' => 'bank', 'opening_balance' => 0, 'is_active' => 1]);
        $kas = Wallet::where('name', 'Kas')->firstOrFail();
        $bca = Wallet::where('name', 'BCA')->firstOrFail();

        $pipe = $this->material(['name' => 'Pipa SS 32', 'warehouse_id' => $raw->id, 'measure' => 'length', 'bar_length_cm' => 600, 'min_reusable_cm' => 5, 'last_price' => 300_000]);
        $plate = $this->material(['name' => 'Plat SS 1 mm', 'warehouse_id' => $raw->id, 'unit' => 'Lembar', 'use_unit' => 'cm²', 'conversion' => 28_800, 'last_price' => 576_000]);
        $pureng = $this->material(['name' => 'Pureng Mio', 'warehouse_id' => $accessory->id, 'last_price' => 10_000, 'min_stock' => 2]);
        $bolt = $this->material(['name' => 'Baut L', 'warehouse_id' => $accessory->id, 'last_price' => 500]);
        $box = $this->material(['name' => 'Dus', 'warehouse_id' => $accessory->id, 'last_price' => 3_000]);

        $this->post(route('admin.vendor-services.store'), ['vendor' => 'Bengkel Bending Jaya', 'process' => 'Bending', 'rate' => 3_000, 'unit' => 'buah'])->assertSessionHasNoErrors();
        $bender = Supplier::where('name', 'Bengkel Bending Jaya')->firstOrFail();
        $this->post(route('admin.workers.store'), ['name' => 'Sidik', 'daily_wage' => 120_000, 'meal_allowance' => 15_000, 'overtime_rate' => 20_000, 'is_active' => 1])->assertSessionHasNoErrors();
        $worker = Worker::firstOrFail();

        // Formula: hanya Leheran & Silincer; aksesoris dari gudangnya; P1 dijasakan.
        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'Matic', 'motor_brand' => 'Yamaha', 'motor_type' => 'Mio', 'variant' => 'Racing', 'name' => 'Mio Racing', 'is_active' => 1,
            'parts' => [
                ['name' => 'Leheran', 'components' => [
                    ['name' => 'P1', 'qty' => 1, 'work_mode' => 'vendor', 'vendor_service_id' => VendorService::firstOrFail()->id, 'materials' => [['material_id' => $pipe->id, 'qty' => 30]]],
                    ['name' => 'P2', 'qty' => 1, 'materials' => [['material_id' => $pipe->id, 'qty' => 20]]],
                    ['name' => 'Pureng', 'qty' => 1, 'materials' => [['material_id' => $pureng->id, 'qty' => 1]]],
                    ['name' => 'Baut', 'qty' => 2, 'materials' => [['material_id' => $bolt->id, 'qty' => 1]]],
                ]],
                ['name' => 'Silincer', 'components' => [
                    ['name' => 'Tabung', 'qty' => 1, 'materials' => [['material_id' => $plate->id, 'qty' => 1_200]]],
                    ['name' => 'Baut', 'qty' => 2, 'materials' => [['material_id' => $bolt->id, 'qty' => 1]]],
                    ['name' => 'Dus', 'qty' => 1, 'materials' => [['material_id' => $box->id, 'qty' => 1]]],
                ]],
            ],
            'costs' => [['name' => 'Ongkos las', 'amount' => 25_000]],
        ])->assertSessionHasNoErrors();
        $formula = Formula::firstOrFail();

        // Rencana 10 → pembelian (bayar sebagian) + baut cadangan 10 (lunas).
        $this->post(route('admin.plans.store'), ['planned_for' => '2026-12-01', 'lines' => [['formula_id' => $formula->id, 'qty' => 10]]])->assertSessionHasNoErrors();
        $plan = ProductionPlan::firstOrFail();
        $this->post(route('admin.purchases.store'), [
            'purchased_at' => '2026-12-01', 'supplier' => 'Toko Besi Jaya', 'production_plan_id' => $plan->id,
            'items' => [
                ['material_id' => $pipe->id, 'qty' => 1, 'bar_length_cm' => 600, 'unit_price' => 300_000],
                ['material_id' => $plate->id, 'qty' => 1, 'unit_price' => 576_000],
                ['material_id' => $pureng->id, 'qty' => 10, 'unit_price' => 10_000],
                ['material_id' => $bolt->id, 'qty' => 40, 'unit_price' => 500],
                ['material_id' => $box->id, 'qty' => 10, 'unit_price' => 3_000],
            ],
            'paid_amount' => 500_000, 'wallet_id' => $kas->id,
        ])->assertSessionHasNoErrors();
        $mainPurchase = MaterialPurchase::firstOrFail();
        $this->post(route('admin.purchases.store'), [
            'purchased_at' => '2026-12-01', 'supplier' => 'Toko Baut',
            'items' => [['material_id' => $bolt->id, 'qty' => 10, 'unit_price' => 500]],
            'paid_amount' => 5_000, 'wallet_id' => $kas->id,
        ])->assertSessionHasNoErrors();
        $boltPurchase = MaterialPurchase::latest('id')->firstOrFail();

        // Retur 4 baut karatan; uang kembali 2.000 ke Kas.
        $this->post(route('admin.purchases.returns.store', $boltPurchase), [
            'returned_at' => '2026-12-02', 'items' => [['material_purchase_item_id' => $boltPurchase->items()->value('id'), 'qty' => 4]],
            'refund_amount' => 2_000, 'wallet_id' => $kas->id, 'notes' => 'karatan',
        ])->assertSessionHasNoErrors();
        $this->assertEquals(46, $bolt->fresh()->load('stocks')->stockIn($accessory));

        // Uang muka ke tukang bending sebelum dikerjakan.
        $this->post(route('admin.suppliers.deposits.store', $bender), ['paid_at' => '2026-12-01', 'amount' => 10_000, 'wallet_id' => $kas->id])->assertSessionHasNoErrors();

        // Produksi dari rencana, penanggung jawab dari daftar Pekerja, sisa pipa kembali ke stok.
        $this->post(route('admin.plans.productions', $plan))->assertRedirect();
        $job = Production::firstOrFail();
        $this->put(route('admin.productions.update', $job), [
            'produced_at' => $job->produced_at->toDateString(), 'formula_id' => $formula->id, 'qty' => 10, 'mode' => 'internal', 'worker_id' => $worker->id,
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.productions.post', $job), ['leftover' => [PipePiece::firstOrFail()->id => 'stok']])->assertSessionMissing('error');
        $job->refresh();
        $this->assertTrue($job->isPosted());
        $this->assertSame('Sidik', $job->worker);
        $this->assertSame($offcut->id, PipePiece::firstOrFail()->warehouse_id);
        $this->assertEquals(92_300, (float) $job->hpp_per_unit);
        $item = $formula->item->fresh();
        $this->assertEquals(10, (float) $item->stock);

        // Hutang: bahan (sisa 526.000) dibayar sekaligus; jasa bending 30.000 pakai DP 10.000 + 20.000.
        $bending = MaterialPurchase::where('kind', 'jasa')->firstOrFail();
        $this->assertSame($bender->id, $bending->supplier_id);
        $this->post(route('admin.suppliers.pay', $bender), ['paid_at' => '2026-12-03', 'purchases' => [$bending->id], 'use_deposit' => 1, 'amount' => 20_000, 'wallet_id' => $kas->id])->assertSessionHasNoErrors();
        $this->post(route('admin.suppliers.pay', Supplier::where('name', 'Toko Besi Jaya')->firstOrFail()), ['paid_at' => '2026-12-03', 'purchases' => [$mainPurchase->id], 'amount' => 526_000, 'wallet_id' => $bca->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(0.0, $bending->fresh()->payable());
        $this->assertSame(0.0, $mainPurchase->fresh()->payable());

        // Penjualan multi-produk + diskon, DP ke Kas, pelunasan ke BCA, lalu resi tracking.
        $beat = Item::create(['sku' => 'KNL-BEAT', 'name' => 'Beat Standar', 'cost_price' => 300_000, 'opening_stock' => 3, 'is_active' => true]);
        $beat->recalculateStock();
        $this->post(route('admin.sales.store'), [
            'sold_at' => '2026-12-04', 'channel' => 'Website', 'customer_name' => 'Budi', 'customer_phone' => '0812',
            'lines' => [['item_id' => $item->id, 'qty' => 4, 'unit_price' => 300_000], ['item_id' => $beat->id, 'qty' => 1, 'unit_price' => 450_000]],
            'discount' => 50_000, 'paid_amount' => 500_000, 'wallet_id' => $kas->id,
        ])->assertSessionHasNoErrors();
        $sale = Sale::firstOrFail();
        $this->assertEquals(1_600_000, (float) $sale->total);
        $this->post(route('admin.sales.payments.store', $sale), ['paid_at' => '2026-12-08', 'amount' => 1_100_000, 'wallet_id' => $bca->id])->assertSessionHasNoErrors();
        $this->assertTrue($sale->fresh()->isPaidOff());
        $this->post(route('admin.orders.store'), ['sale_id' => $sale->id, 'order_number' => $sale->invoice_number, 'status' => 'shipped', 'customer_name' => 'Budi'])->assertSessionHasNoErrors();

        // Stok opname per gudang dengan pelaksana.
        $this->post(route('admin.stock-opname.items'), ['adjusted_at' => '2026-12-09', 'reason' => 'rusak', 'counted_by' => 'Sidik', 'actual' => [$item->id => 5, $beat->id => 2]])->assertSessionHasNoErrors();
        $this->assertEquals(5, (float) $item->fresh()->stock);
        $this->post(route('admin.stock-opname.materials'), ['warehouse' => 'aksesoris', 'adjusted_at' => '2026-12-09', 'counted_by' => 'Rudi', 'actual' => [$bolt->id => 5]])->assertSessionHasNoErrors();
        $this->assertEquals(5, $bolt->fresh()->load('stocks')->stockIn($accessory));
        $this->assertSame(1, ItemAdjustment::count());
        $this->assertSame('Rudi', MaterialAdjustment::firstOrFail()->counted_by);

        // Pekerja: kasbon, absensi seminggu (1 hari setengah, 2 jam lembur), gaji dibayar.
        $this->post(route('admin.workers.advances.store', $worker), ['given_at' => '2026-12-02', 'amount' => 100_000, 'wallet_id' => $kas->id])->assertSessionHasNoErrors();
        foreach (['2026-11-30' => 'hadir', '2026-12-01' => 'hadir', '2026-12-02' => 'setengah', '2026-12-03' => 'hadir', '2026-12-04' => 'hadir'] as $day => $status) {
            $this->post(route('admin.attendances.store'), ['worked_on' => $day, 'rows' => [$worker->id => ['status' => $status, 'overtime_hours' => $day === '2026-12-04' ? 2 : 0]]])->assertSessionHasNoErrors();
        }
        $this->post(route('admin.payrolls.store'), ['worker_id' => $worker->id, 'period_start' => '2026-11-30', 'period_end' => '2026-12-06'])->assertSessionHasNoErrors();
        $slip = WorkerPayroll::firstOrFail();
        // 4,5 hari × 120.000 + makan 5 × 15.000 + lembur 2 × 20.000 − kasbon 100.000.
        $this->assertEquals(540_000 + 75_000 + 40_000 - 100_000, (float) $slip->net_pay);
        $this->post(route('admin.payrolls.pay', $slip), ['paid_at' => '2026-12-06', 'wallet_id' => $kas->id])->assertSessionHasNoErrors();

        $this->assertNumbersAgree();

        // Saldo akhir: Kas = 10 jt − 500.000 − 5.000 + 2.000 − DP 10.000 − 20.000 + 500.000 − kasbon 100.000 − gaji 555.000.
        $this->assertEquals(10_000_000 - 500_000 - 5_000 + 2_000 - 10_000 - 20_000 + 500_000 - 100_000 - 555_000, (float) $kas->fresh()->current_balance);
        $this->assertEquals(-526_000 + 1_100_000, (float) $bca->fresh()->current_balance);

        // Setiap halaman: pemilik bisa semua; peran lain sesuai hak aksesnya, tidak ada yang error.
        $this->crawlAs(auth()->user());
        foreach (['admin', 'gudang', 'kasir'] as $role) {
            $this->crawlAs(User::factory()->create(['role' => $role]));
        }
    }

    /** Pengecekan silang antar modul. */
    private function assertNumbersAgree(): void
    {
        foreach (Wallet::all() as $wallet) {
            $in = (float) WalletTransaction::where('wallet_id', $wallet->id)->where('direction', 'in')->sum('amount');
            $out = (float) WalletTransaction::where('wallet_id', $wallet->id)->where('direction', 'out')->sum('amount');
            $this->assertEqualsWithDelta((float) $wallet->opening_balance + $in - $out, (float) $wallet->fresh()->current_balance, 0.01, "Saldo {$wallet->name}");
        }

        foreach (Material::with('stocks')->get() as $material) {
            $this->assertEqualsWithDelta((float) $material->stock, (float) $material->stocks->sum('qty'), 0.001, "Stok {$material->name}");
            $this->assertGreaterThanOrEqual(-0.01, (float) $material->stocks->min('value'), "Nilai stok {$material->name}");
        }

        foreach (Item::all() as $item) {
            $this->assertEqualsWithDelta($item->computedStock(), (float) $item->stock, 0.001, "Stok {$item->name}");
        }

        foreach (MaterialPurchase::all() as $purchase) {
            $this->assertEqualsWithDelta((float) $purchase->payments()->sum('amount'), (float) $purchase->paid_amount, 0.01, $purchase->invoice_number);
            $this->assertLessThanOrEqual((float) $purchase->total + 0.01, (float) $purchase->paid_amount, $purchase->invoice_number);
        }

        foreach (Sale::all() as $sale) {
            $this->assertEqualsWithDelta((float) $sale->payments()->sum('amount'), (float) $sale->paid_amount, 0.01, $sale->invoice_number);
        }

        foreach (Supplier::all() as $supplier) {
            $this->assertGreaterThanOrEqual(-0.01, $supplier->depositBalance(), $supplier->name);
        }

        // Uang masuk/keluar di dashboard = mutasi dompet (di luar transfer antar dompet & mutasi manual).
        $headline = app(DashboardSummary::class)->headline();
        $business = WalletTransaction::query()->whereNotNull('source_type')->where('source_type', '!=', WalletTransfer::class);
        $refunds = (float) (clone $business)->where('source_type', PurchasePayment::class)->where('direction', 'in')->sum('amount');
        $this->assertEqualsWithDelta((float) (clone $business)->where('direction', 'out')->sum('amount') - $refunds, $headline['expenses'], 0.01, 'Uang keluar dashboard');
        $this->assertEqualsWithDelta((float) (clone $business)->where('source_type', SalePayment::class)->sum('amount'), $headline['cash_in'], 0.01, 'Uang masuk dashboard');
        $this->assertSame(Order::firstOrFail()->sale_id, Sale::firstOrFail()->id);
    }

    /** Buka setiap halaman GET admin sebagai pengguna ini. */
    private function crawlAs(User $user): void
    {
        $this->actingAs($user);
        $types = ['bahan-baku', 'setengah-jadi', 'aksesoris', 'stok-sisa', 'di-vendor', 'finish-good'];
        $failures = [];
        $visited = 0;
        $forbidden = 0;

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $sets = [[]];
            foreach ($route->parameterNames() as $param) {
                if ($param === 'type') {
                    $sets = collect($sets)->flatMap(fn ($set) => collect($types)->map(fn ($type) => $set + ['type' => $type]))->all();

                    continue;
                }
                $class = 'App\\Models\\'.Str::studly(['webProduct' => 'product', 'plan' => 'production_plan', 'payroll' => 'worker_payroll', 'purchase' => 'material_purchase', 'receipt' => 'purchase_receipt', 'transfer' => 'stock_transfer'][$param] ?? $param);
                $record = class_exists($class) ? $class::query()->first() : null;
                if (! $record) {
                    continue 2;
                }
                $sets = collect($sets)->map(fn ($set) => $set + [$param => $record->getRouteKey()])->all();
            }

            foreach ($sets as $params) {
                $response = $this->get(route($name, $params));
                $visited++;
                $forbidden += $response->status() === 403 ? 1 : 0;
                $expected = AdminNavigation::allows($user, $name, $params) ? 200 : 403;
                if ($response->status() !== $expected && ! ($expected === 200 && $response->isRedirect())) {
                    $failures[] = "[{$user->role}] {$name} ".json_encode($params).' → '.$response->status().' (harusnya '.$expected.')'
                        .($response->exception && $response->status() >= 500 ? ' '.Str::limit($response->exception->getMessage(), 160) : '');
                }
            }
        }

        $this->assertSame([], $failures, "Halaman bermasalah:\n".implode("\n", $failures));
        $this->assertGreaterThan(75, $visited, 'Jumlah halaman yang dibuka');

        if (in_array($user->role, ['gudang', 'kasir'], true)) {
            $this->assertGreaterThan(10, $forbidden);
        } else {
            $this->assertSame($user->isOwner() ? 0 : 1, $forbidden);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function material(array $data): Material
    {
        $this->post(route('admin.materials.store'), $data + ['measure' => 'unit', 'unit' => 'Pcs', 'is_active' => 1])->assertSessionHasNoErrors();

        return Material::where('name', $data['name'])->firstOrFail();
    }
}
