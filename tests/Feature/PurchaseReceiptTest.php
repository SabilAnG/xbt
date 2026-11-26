<?php

namespace Tests\Feature;

use App\Models\Formula;
use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Models\ProductionPlan;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Warehouse;
use App\Services\ProductionPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Alur EMS: Pembelian (pesan) → Penerimaan (barang datang, bertahap) → stok; Supplier sebagai master. */
class PurchaseReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    private function order(Material $bolt, Material $pipe, array $extra = []): MaterialPurchase
    {
        $this->post(route('admin.purchases.store'), $extra + [
            'purchased_at' => '2026-12-01', 'supplier' => 'Toko Besi Jaya', 'buyer' => 'SIDIK', 'receive_now' => '0',
            'items' => [
                ['material_id' => $bolt->id, 'qty' => 100, 'unit_price' => 500],
                ['material_id' => $pipe->id, 'qty' => 3, 'bar_length_cm' => 600, 'unit_price' => 300_000],
            ],
        ])->assertSessionHasNoErrors();

        return MaterialPurchase::latest('id')->firstOrFail();
    }

    public function test_ordered_goods_enter_stock_only_when_received_and_can_arrive_in_stages(): void
    {
        $raw = Warehouse::forType(Warehouse::RAW);
        $bolt = Material::factory()->create(['name' => 'Baut M6']);
        $pipe = Material::factory()->pipe()->create(['name' => 'Pipa SS 2"']);
        $purchase = $this->order($bolt, $pipe);
        [$boltLine, $pipeLine] = $purchase->items->sortBy('id')->values()->all();

        $this->assertSame('BELUM DITERIMA', $purchase->receiveStatus());
        $this->assertEquals(0, $bolt->fresh()->stock);
        $this->assertEquals(900_000 + 50_000, (float) $purchase->total, 'Hutang dihitung dari pesanan.');
        $this->assertSame('Toko Besi Jaya', $purchase->supplierRecord->name);
        $this->get(route('admin.receipts.index'))->assertOk()->assertSee($purchase->invoice_number);

        // Datang pertama: 60 baut dan 2 batang pipa.
        $this->get(route('admin.receipts.create', ['pembelian' => $purchase->id]))->assertOk()->assertSee('Baut M6');
        $this->post(route('admin.receipts.store'), [
            'material_purchase_id' => $purchase->id, 'received_at' => '2026-12-03', 'received_by' => 'ANDI',
            'items' => [
                ['material_purchase_item_id' => $boltLine->id, 'qty' => 60, 'warehouse_id' => $raw->id],
                ['material_purchase_item_id' => $pipeLine->id, 'qty' => 2],
            ],
        ])->assertSessionHasNoErrors();

        $purchase->refresh();
        $this->assertSame('DITERIMA SEBAGIAN', $purchase->receiveStatus());
        $this->assertEquals(60, $bolt->fresh()->load('stocks')->stockIn($raw));
        $this->assertEquals(1_200, $pipe->fresh()->load('stocks')->stockIn($raw));
        $this->assertSame(2, $pipe->pieces()->count());

        // Menerima lebih dari sisa pesanan ditolak.
        $this->post(route('admin.receipts.store'), [
            'material_purchase_id' => $purchase->id, 'received_at' => '2026-12-04',
            'items' => [['material_purchase_item_id' => $boltLine->id, 'qty' => 41]],
        ])->assertSessionHas('error');
        $this->assertEquals(60, $bolt->fresh()->stock);

        // Sisa pipa tidak jadi datang → dibatalkan, tagihan turun.
        $this->post(route('admin.receipts.store'), [
            'material_purchase_id' => $purchase->id, 'received_at' => '2026-12-05',
            'items' => [['material_purchase_item_id' => $boltLine->id, 'qty' => 40], ['material_purchase_item_id' => $pipeLine->id, 'qty' => 0]],
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.purchases.cancel-outstanding', $purchase))->assertSessionHasNoErrors();

        $purchase->refresh();
        $this->assertSame('DITERIMA', $purchase->receiveStatus());
        $this->assertEquals(600_000 + 50_000, (float) $purchase->total);
        $this->assertSame(2, PurchaseReceipt::count());

        // Hapus penerimaan pertama: stoknya ditarik lagi dan barang kembali ditunggu.
        $first = PurchaseReceipt::oldest('id')->firstOrFail();
        $this->delete(route('admin.receipts.destroy', $first))->assertSessionHasNoErrors();
        $this->assertEquals(40, $bolt->fresh()->stock);
        $this->assertSame(0, $pipe->pieces()->count());
        $this->assertSame('DITERIMA SEBAGIAN', $purchase->fresh()->receiveStatus());
    }

    public function test_shop_purchase_is_received_immediately_by_default(): void
    {
        $bolt = Material::factory()->create();
        $pipe = Material::factory()->pipe()->create();
        $this->post(route('admin.wallets.store'), ['name' => 'Kas', 'type' => 'cash', 'opening_balance' => 1_000_000, 'is_active' => 1]);
        $purchase = $this->order($bolt, $pipe, ['receive_now' => '1', 'paid_amount' => 50_000, 'wallet_id' => Wallet::where('name', 'Kas')->value('id')]);

        $this->assertSame('DITERIMA', $purchase->receiveStatus());
        $this->assertEquals(100, $bolt->fresh()->stock);
        $this->assertSame('SIDIK', $purchase->receipts->first()->received_by);
        $this->assertEquals(900_000, $purchase->payable());
    }

    public function test_plan_counts_goods_already_ordered_so_they_are_not_bought_twice(): void
    {
        $bolt = Material::factory()->create(['name' => 'Baut M6']);
        $pipe = Material::factory()->pipe()->create();
        $formula = Formula::create(['code' => 'F-UJI', 'name' => 'Knalpot Uji', 'output_type' => Formula::KNALPOT]);
        $part = $formula->parts()->create(['name' => 'Leheran', 'sort_order' => 1]);
        $component = $part->components()->create(['name' => 'P1', 'sort_order' => 1]);
        $component->materials()->create(['material_id' => $bolt->id, 'qty' => 150]);
        $plan = ProductionPlan::create(['plan_number' => 'RP-TEST', 'planned_for' => '2026-12-10']);
        $plan->lines()->create(['formula_id' => $formula->id, 'qty' => 1]);

        $this->order($bolt, $pipe);
        $row = app(ProductionPlanner::class)->forPlan($plan->fresh())['rows']->firstWhere('material.id', $bolt->id);

        $this->assertEquals(100, $row['on_order']);
        $this->assertEquals(50, $row['buy_qty']);
    }

    public function test_supplier_master_collects_purchases_vendor_bills_and_debt(): void
    {
        $bolt = Material::factory()->create();
        $pipe = Material::factory()->pipe()->create();
        $purchase = $this->order($bolt, $pipe);
        $supplier = Supplier::where('name', 'Toko Besi Jaya')->firstOrFail();

        // Nama ditulis beda huruf besar-kecil tetap supplier yang sama.
        $this->order($bolt, $pipe, ['supplier' => 'toko besi jaya']);
        $this->assertSame(1, Supplier::count());
        $this->assertSame(2, $supplier->purchases()->count());

        $this->get(route('admin.suppliers.index'))->assertOk()->assertSee('Toko Besi Jaya');
        $this->get(route('admin.suppliers.show', $supplier))->assertOk()->assertSee($purchase->invoice_number);
        $this->assertEquals(2 * 950_000, $supplier->payable());

        // Ganti nama: nota ikut, dan supplier dengan pembelian tidak bisa dihapus.
        $this->put(route('admin.suppliers.update', $supplier), ['name' => 'CV Besi Jaya', 'phone' => '0812', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame('CV Besi Jaya', $purchase->fresh()->supplier);
        $this->delete(route('admin.suppliers.destroy', $supplier))->assertSessionHas('error');

        $this->post(route('admin.suppliers.store'), ['name' => 'Bengkel Las Maju'])->assertSessionHasNoErrors();
        $this->delete(route('admin.suppliers.destroy', Supplier::where('name', 'Bengkel Las Maju')->first()))->assertSessionHasNoErrors();
        $this->assertSame(1, Supplier::count());
    }
}
