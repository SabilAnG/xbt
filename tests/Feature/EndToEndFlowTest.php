<?php

namespace Tests\Feature;

use App\Models\Formula;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\MaterialPurchase;
use App\Models\MaterialStock;
use App\Models\PipePiece;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Warehouse;
use App\Services\ProductionPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Skenario lengkap satu siklus usaha, dari master item sampai laporan
 * keuangan, untuk memastikan semua modul saling sinkron.
 *
 * Kerf memakai bawaan 3 mm (0,3 cm per potong).
 */
class EndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_full_cycle_from_master_items_to_finance_stays_in_sync(): void
    {
        $raw = Warehouse::forType(Warehouse::RAW);
        $semi = Warehouse::forType(Warehouse::SEMI);
        $accessory = Warehouse::forType(Warehouse::ACCESSORY);
        $offcut = Warehouse::forType(Warehouse::OFFCUT);

        // 1. Master item & satuan --------------------------------------------
        $pipe = $this->item(['name' => 'Pipa SS 1,25"', 'warehouse_id' => $raw->id, 'measure' => 'length', 'bar_length_cm' => 600, 'min_reusable_cm' => 5, 'last_price' => 300_000]);
        $plate = $this->item(['name' => 'Plat SS 1 mm', 'warehouse_id' => $raw->id, 'unit' => 'Lembar', 'use_unit' => 'cm²', 'conversion' => 28_800, 'last_price' => 576_000]);
        $pureng = $this->item(['name' => 'Pureng Mio', 'warehouse_id' => $semi->id, 'unit' => 'Pcs', 'last_price' => 10_000]);
        $plendes = $this->item(['name' => 'Plendes 28', 'warehouse_id' => $semi->id, 'unit' => 'Pcs', 'last_price' => 15_000]);
        $bolt = $this->item(['name' => 'Baut L', 'warehouse_id' => $accessory->id, 'unit' => 'Pcs', 'last_price' => 500]);
        $box = $this->item(['name' => 'Dus', 'warehouse_id' => $accessory->id, 'unit' => 'Pcs', 'last_price' => 3_000]);

        $this->post(route('admin.wallets.store'), ['name' => 'Kas', 'type' => 'cash', 'opening_balance' => 5_000_000, 'is_active' => 1]);
        $cash = Wallet::where('name', 'Kas')->firstOrFail();

        // 2. Formula setengah jadi (dikerjakan vendor) & formula knalpot ----
        $this->post(route('admin.formulas.store'), [
            'output_type' => 'setengah_jadi', 'motor_category' => 'matic', 'motor_type' => 'Mio', 'name' => 'Leheran Mio bending', 'is_active' => 1,
            'parts' => [['name' => 'Bahan', 'components' => [
                ['name' => 'P1', 'qty' => 1, 'materials' => [['material_id' => $pipe->id, 'qty' => 30]]],
                ['name' => 'P2', 'qty' => 1, 'materials' => [['material_id' => $pipe->id, 'qty' => 20]]],
                ['name' => 'Plendes', 'qty' => 1, 'materials' => [['material_id' => $plendes->id, 'qty' => 1]]],
            ]]],
        ])->assertSessionHasNoErrors();
        $leheranFormula = Formula::where('name', 'Leheran Mio bending')->firstOrFail();
        $leheran = $leheranFormula->outputMaterial;
        $this->assertSame($semi->id, $leheran->warehouse_id);

        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'matic', 'motor_type' => 'Mio', 'variant' => 'Racing Sarangan Tengah', 'name' => 'Mio Racing Sarangan Tengah', 'is_active' => 1,
            'parts' => [
                ['name' => 'Leheran', 'components' => [
                    ['name' => 'Leheran bending', 'qty' => 1, 'materials' => [['material_id' => $leheran->id, 'qty' => 1]]],
                    ['name' => 'Pureng', 'qty' => 1, 'materials' => [['material_id' => $pureng->id, 'qty' => 1]]],
                ]],
                ['name' => 'Silincer', 'components' => [
                    ['name' => 'Tabung', 'qty' => 1, 'materials' => [['material_id' => $plate->id, 'qty' => 1_200]]],
                ]],
                ['name' => 'Aksesoris', 'components' => [
                    ['name' => 'Baut', 'qty' => 4, 'materials' => [['material_id' => $bolt->id, 'qty' => 1]]],
                    ['name' => 'Dus', 'qty' => 1, 'materials' => [['material_id' => $box->id, 'qty' => 1]]],
                ]],
            ],
            'costs' => [['name' => 'Ongkos las', 'amount' => 25_000]],
        ])->assertSessionHasNoErrors();
        $knalpot = Formula::where('name', 'Mio Racing Sarangan Tengah')->firstOrFail();
        $this->assertNotNull($knalpot->item_id);

        // 3. Rencana 10 leheran + 10 knalpot --------------------------------
        $this->post(route('admin.plans.store'), [
            'planned_for' => '2026-12-01', 'title' => 'Batch Mio 1',
            'lines' => [['formula_id' => $leheranFormula->id, 'qty' => 10], ['formula_id' => $knalpot->id, 'qty' => 10]],
        ])->assertSessionHasNoErrors();
        $plan = ProductionPlan::firstOrFail();
        $needs = app(ProductionPlanner::class)->forPlan($plan)['rows']->keyBy(fn ($row) => $row['material']->id);

        // 10 × (30 + 20) cm = 500 cm → 1 batang; 10 × 1.200 cm² = 12.000 cm² → 1 lembar.
        $this->assertSame(1, $needs[$pipe->id]['buy_qty']);
        $this->assertEquals(1, $needs[$plate->id]['buy_qty']);
        $this->assertEquals(40, $needs[$bolt->id]['buy_qty']);
        // Leheran bending dibuat dari baris rencana pertama, jadi tidak perlu dibeli.
        $this->assertEquals(0, $needs[$leheran->id]['buy_qty']);
        $this->assertEquals(10, $needs[$leheran->id]['planned']);

        // 4. Pembelian dari kekurangan rencana, dibayar sebagian ------------
        $this->get(route('admin.purchases.create', ['rencana' => $plan->id]))->assertOk()
            ->assertViewHas('lines', fn (array $lines) => collect($lines)->pluck('material_id')->sort()->values()->all()
                === collect([$pipe->id, $plate->id, $pureng->id, $plendes->id, $bolt->id, $box->id])->sort()->values()->all());
        $this->post(route('admin.purchases.store'), [
            'purchased_at' => '2026-12-01', 'supplier' => 'Toko Besi Jaya', 'buyer' => 'SIDIK', 'production_plan_id' => $plan->id,
            'items' => [
                ['material_id' => $pipe->id, 'qty' => 1, 'bar_length_cm' => 600, 'unit_price' => 300_000],
                ['material_id' => $plate->id, 'qty' => 1, 'unit_price' => 576_000],
                ['material_id' => $pureng->id, 'qty' => 10, 'unit_price' => 10_000],
                ['material_id' => $plendes->id, 'qty' => 10, 'unit_price' => 15_000],
                ['material_id' => $bolt->id, 'qty' => 40, 'unit_price' => 500],
                ['material_id' => $box->id, 'qty' => 10, 'unit_price' => 3_000],
            ],
            'paid_amount' => 1_000_000, 'wallet_id' => $cash->id,
        ])->assertSessionHasNoErrors();
        $purchase = MaterialPurchase::firstOrFail();
        $this->assertEquals(1_176_000, (float) $purchase->total);
        $this->assertEquals(176_000, $purchase->payable());
        $this->assertEquals(600, $pipe->fresh()->load('stocks')->stockIn($raw));
        $this->assertEquals(28_800, $plate->fresh()->load('stocks')->stockIn($raw));
        $this->assertEquals(40, $bolt->fresh()->load('stocks')->stockIn($accessory));

        // 5. Produksi dari rencana; leheran dikerjakan vendor ---------------
        $this->post(route('admin.plans.productions', $plan))->assertRedirect();
        $leheranJob = Production::where('formula_id', $leheranFormula->id)->firstOrFail();
        $knalpotJob = Production::where('formula_id', $knalpot->id)->firstOrFail();

        $this->put(route('admin.productions.update', $leheranJob), [
            'produced_at' => '2026-12-02', 'formula_id' => $leheranFormula->id, 'qty' => 10,
            'mode' => 'vendor', 'vendor' => 'Bengkel Joko', 'process' => 'Bending', 'service_cost_per_unit' => 15_000,
        ])->assertSessionHasNoErrors();

        // Knalpot belum bisa dibukukan: leheran bending belum ada.
        $this->post(route('admin.productions.post', $knalpotJob))->assertSessionHas('error');

        $this->post(route('admin.productions.post', $leheranJob))->assertSessionMissing('error');
        $leheranJob->refresh();
        // 20 potong × 0,3 cm serbuk = 6 cm → terpakai 506 cm, sisa 94 cm ke Stok Sisa.
        $bar = PipePiece::firstOrFail();
        $this->assertEqualsWithDelta(94, (float) $bar->length_cm, 0.001);
        $this->assertSame($offcut->id, $bar->warehouse_id);
        // Bahan: 506 cm × Rp 500 + 10 plendes × 15.000 = 403.000 → 40.300 / unit, sedang di vendor.
        $this->assertEquals(403_000, (float) $leheranJob->material_cost);
        $this->assertEquals(403_000, $leheranJob->outstandingValue());

        // 6. Terima dari vendor bertahap: 6 lalu 4 ---------------------------
        $this->post(route('admin.productions.receive', $leheranJob), ['received_at' => '2026-12-05', 'qty' => 6, 'service_cost_per_unit' => 15_000])->assertSessionMissing('error');
        $this->post(route('admin.productions.receive', $leheranJob), ['received_at' => '2026-12-06', 'qty' => 4, 'service_cost_per_unit' => 15_000])->assertSessionMissing('error');
        $this->assertSame('SELESAI', $leheranJob->fresh()->statusLabel());
        $this->assertEquals(10, $leheran->fresh()->load('stocks')->stockIn($semi));
        $this->assertEquals(55_300, (float) $leheran->fresh()->avg_cost, 'bahan 40.300 + jasa 15.000');
        $bills = MaterialPurchase::where('kind', 'jasa')->orderBy('id')->get();
        $this->assertEquals([90_000, 60_000], $bills->map(fn ($bill) => (float) $bill->total)->all());

        // 7. Produksi knalpot --------------------------------------------------
        $this->post(route('admin.productions.post', $knalpotJob))->assertSessionMissing('error');
        $knalpotJob->refresh();
        // 553.000 leheran + 100.000 pureng + 12.000 cm² × 20 + 20.000 baut + 30.000 dus = 943.000; ongkos 250.000.
        $this->assertEquals(943_000, (float) $knalpotJob->material_cost);
        $this->assertEquals(1_193_000, (float) $knalpotJob->total_cost);
        $this->assertEquals(119_300, (float) $knalpotJob->hpp_per_unit);
        $item = $knalpot->item->fresh();
        $this->assertEquals(10, (float) $item->stock);
        $this->assertEquals(119_300, (float) $item->cost_price);
        $this->assertSame('selesai', $plan->fresh()->status);

        // 8. Jual 3 knalpot, bayar tagihan vendor pertama ---------------------
        $this->post(route('admin.sales.store'), [
            'sold_at' => '2026-12-08', 'item_id' => $item->id, 'qty' => 3, 'unit_price' => 350_000, 'channel' => 'Offline',
            'paid_amount' => 1_050_000, 'wallet_id' => $cash->id,
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.purchases.payments.store', $bills[0]), ['paid_at' => '2026-12-08', 'amount' => 90_000, 'wallet_id' => $cash->id])->assertSessionMissing('error');

        // 9. Keuangan ---------------------------------------------------------
        $this->assertEquals(5_000_000 - 1_000_000 - 90_000 + 1_050_000, (float) $cash->fresh()->current_balance);
        $this->assertEquals(7, (float) $item->fresh()->stock);

        $report = $this->get(route('admin.finance.index', ['dari' => '2026-12-01', 'sampai' => '2026-12-31']))->assertOk();
        $report->assertSee('Rp 1.050.000')   // omzet
            ->assertSee('Rp 357.900')        // HPP 3 × 119.300
            ->assertSee('Rp 692.100')        // laba kotor
            ->assertSee('Rp 4.960.000')      // kas
            ->assertSee('Rp 236.000');       // hutang 176.000 + 60.000

        // 10. Sinkron antar modul --------------------------------------------
        foreach (Material::with('stocks')->get() as $material) {
            $this->assertEqualsWithDelta((float) $material->stock, (float) $material->stocks->sum('qty'), 0.001, "stok total = stok per gudang: {$material->name}");

            foreach ($material->stocks as $stock) {
                $card = MaterialMovement::where('material_id', $material->id)->where('warehouse_id', $stock->warehouse_id);
                $this->assertEqualsWithDelta((float) $stock->qty, (float) $card->sum('qty_in') - (float) $card->sum('qty_out'), 0.001, "kartu stok = stok gudang: {$material->name}");
            }

            if ($material->isPipe()) {
                $this->assertEqualsWithDelta((float) $material->stock, (float) $material->availablePieces()->sum('length_cm'), 0.001, 'stok pipa = potongan tersedia');
            }
        }

        // Nilai masuk (pembelian + jasa vendor + ongkos) = persediaan tersisa + HPP terjual.
        $inventory = (float) MaterialStock::sum('value') + (float) $item->fresh()->stockValue();
        $this->assertEqualsWithDelta(1_176_000 + 150_000 + 250_000, $inventory + 357_900, 1);
        $this->assertEqualsWithDelta(47_000, (float) MaterialStock::where('warehouse_id', $offcut->id)->sum('value'), 1, '94 cm × Rp 500 di Stok Sisa');

        // Semua halaman terkait terbuka dengan data ini.
        foreach ([
            route('admin.master-items.index'), route('admin.units.index'), route('admin.warehouses.index'),
            route('admin.warehouses.show', 'stok-sisa'), route('admin.warehouses.show', 'di-vendor'), route('admin.warehouses.show', 'aksesoris'),
            route('admin.plans.show', $plan), route('admin.productions.show', $leheranJob), route('admin.productions.show', $knalpotJob),
            route('admin.purchases.show', $purchase), route('admin.purchases.show', $bills[0]), route('admin.materials.show', $pipe),
            route('admin.formulas.show', $knalpot), route('admin.items.show', $item), route('admin.dashboard'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    /** @param  array<string, mixed>  $data */
    private function item(array $data): Material
    {
        $this->post(route('admin.materials.store'), $data + ['measure' => 'unit', 'unit' => 'Pcs', 'is_active' => 1])->assertSessionHasNoErrors();

        return Material::where('name', $data['name'])->firstOrFail();
    }
}
