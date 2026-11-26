<?php

namespace Tests\Feature;

use App\Models\Formula;
use App\Models\Material;
use App\Models\MaterialAdjustment;
use App\Models\MaterialPurchase;
use App\Models\PipePiece;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorService;
use App\Models\Wallet;
use App\Models\Warehouse;
use App\Services\ProductionPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur inventori dari formula sampai keuangan: formula → pembelian → rencana
 * → produksi (potong pipa, sisa ke Stok Sisa) → Finish Good.
 */
class InventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    private Material $pipe;

    private Material $pureng;

    private Material $plendes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
        // Kerf 0 supaya angka potong mudah dibaca.
        Setting::updateOrCreate(['key' => 'produksi.kerf_mm'], ['value' => '0', 'type' => 'text', 'group' => 'produksi']);

        // 1 batang 600 cm = Rp 300.000 → Rp 500/cm.
        $this->pipe = Material::factory()->pipe(600, 300_000, minReusable: 5)->create(['name' => 'Pipa SS 1,25']);
        $this->pureng = Material::factory()->semiFinished(10_000)->create(['name' => 'Pureng Mio']);
        $this->plendes = Material::factory()->semiFinished(15_000)->create(['name' => 'Plendes 28']);
    }

    public function test_formula_is_saved_per_part_component_and_material_and_prices_follow_materials(): void
    {
        $response = $this->post(route('admin.formulas.store'), [
            'motor_category' => 'matic',
            'motor_type' => 'Mio',
            'variant' => 'Racing Sarangan Tengah',
            'name' => 'Mio · Racing Sarangan Tengah',
            'is_active' => 1,
            'parts' => [
                ['name' => 'Leheran', 'components' => [
                    ['name' => 'Pureng', 'qty' => 1, 'materials' => [['material_id' => $this->pureng->id, 'qty' => 1]]],
                    ['name' => 'Plendes', 'qty' => 1, 'materials' => [['material_id' => $this->plendes->id, 'qty' => 1]]],
                    ['name' => 'P1', 'qty' => 1, 'materials' => [['material_id' => $this->pipe->id, 'qty' => 30], ['material_id' => '', 'qty' => '']]],
                    ['name' => 'P2', 'qty' => 1, 'materials' => [['material_id' => $this->pipe->id, 'qty' => 20]]],
                ]],
                ['name' => 'Silincer', 'components' => [['name' => '', 'qty' => 1, 'materials' => []]]],
            ],
            'costs' => [['name' => 'Ongkos las', 'amount' => 25_000], ['name' => '', 'amount' => '']],
        ]);

        $formula = Formula::firstOrFail();
        $response->assertRedirect(route('admin.formulas.show', $formula));

        $this->assertSame(['Leheran', 'Silincer'], $formula->parts->pluck('name')->all());
        $this->assertSame(['Pureng', 'Plendes', 'P1', 'P2'], $formula->parts[0]->components->pluck('name')->all());
        $this->assertCount(1, $formula->costs);

        // Bahan: 10.000 + 15.000 + (30 + 20) cm × Rp 500 = 50.000; ongkos 25.000.
        $this->assertEquals(50_000, $formula->materialCostPerUnit());
        $this->assertEquals(75_000, $formula->estimatedHpp());

        // Produk jadi dibuat otomatis untuk Gudang Finish Good.
        $this->assertNotNull($formula->item_id);
        $this->assertSame('Mio · Racing Sarangan Tengah', $formula->item->name);

        $this->get(route('admin.formulas.show', $formula))->assertOk()->assertSee('P1')->assertSee('Rp 75.000');
        $this->get(route('admin.formulas.edit', $formula))->assertOk();
    }

    public function test_formula_rejects_material_rows_without_quantity(): void
    {
        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'matic', 'motor_type' => 'Mio', 'name' => 'Mio',
            'parts' => [['name' => 'Leheran', 'components' => [
                ['name' => 'P1', 'qty' => 1, 'materials' => [['material_id' => $this->pipe->id, 'qty' => '']]],
            ]]],
        ])->assertSessionHasErrors('parts.0.components.0.materials.0.qty');

        $this->assertDatabaseCount('formulas', 0);
    }

    public function test_purchase_puts_pipe_bars_and_units_into_their_warehouses_and_tracks_debt(): void
    {
        $wallet = Wallet::create(['name' => 'Kas', 'type' => 'cash', 'opening_balance' => 1_000_000, 'is_active' => true]);

        $this->post(route('admin.purchases.store'), [
            'purchased_at' => '2026-11-26',
            'supplier' => 'Toko Besi',
            'items' => [
                ['material_id' => $this->pipe->id, 'qty' => 2, 'bar_length_cm' => 600, 'unit_price' => 360_000],
                ['material_id' => $this->pureng->id, 'qty' => 10, 'unit_price' => 12_000],
            ],
            'paid_amount' => 500_000,
            'wallet_id' => $wallet->id,
        ])->assertRedirect();

        $purchase = MaterialPurchase::firstOrFail();
        $this->assertEquals(840_000, (float) $purchase->total);
        $this->assertEquals(340_000, $purchase->payable());

        $raw = Warehouse::forType(Warehouse::RAW);
        $this->assertSame(2, PipePiece::where('material_id', $this->pipe->id)->where('warehouse_id', $raw->id)->count());
        $this->assertEquals(1_200, (float) $this->pipe->fresh()->stock);
        $this->assertEquals(600, (float) $this->pipe->fresh()->avg_cost);
        $this->assertEquals(10, $this->pureng->fresh()->stockIn(Warehouse::forType(Warehouse::SEMI)));
        $this->assertEquals(500_000, $wallet->fresh()->transactions()->sum('amount'));

        $this->post(route('admin.purchases.payments.store', $purchase), ['paid_at' => '2026-11-27', 'amount' => 340_000, 'wallet_id' => $wallet->id])->assertRedirect();
        $this->assertSame('LUNAS', $purchase->fresh()->paymentStatus());

        $this->post(route('admin.purchases.payments.store', $purchase), ['paid_at' => '2026-11-27', 'amount' => 1, 'wallet_id' => $wallet->id])
            ->assertSessionHas('error');
    }

    public function test_posting_production_cuts_offcuts_first_moves_leftovers_and_books_finished_goods(): void
    {
        $formula = $this->formula();
        $this->buy([[$this->pipe, 1], [$this->pureng, 5], [$this->plendes, 5]]);
        // Potongan sisa lama 33 cm di Stok Sisa, harga sama.
        $offcut = Warehouse::forType(Warehouse::OFFCUT);
        $this->post(route('admin.adjustments.store'), [
            'adjusted_at' => '2026-11-26', 'material_id' => $this->pipe->id, 'direction' => 'in', 'reason' => 'stok_awal',
            'warehouse_id' => $offcut->id, 'qty' => 1, 'length_cm' => 33, 'unit_cost' => 500,
        ])->assertSessionHasNoErrors();

        $production = $this->draftProduction($formula, 2);
        $this->get(route('admin.productions.show', $production))->assertOk()->assertSee('Rencana potong');

        $this->post(route('admin.productions.post', $production))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $production->refresh();

        $this->assertTrue($production->isPosted());
        // 2 knalpot × (P1 30 cm + P2 20 cm). P1 #1 diambil dari sisa 33 cm → sisa 3 cm diafkir (< 5 cm).
        $old = PipePiece::where('initial_length_cm', 33)->firstOrFail();
        $this->assertSame(PipePiece::SCRAP, $old->status);
        $bar = PipePiece::where('initial_length_cm', 600)->firstOrFail();
        $this->assertEqualsWithDelta(600 - 30 - 20 - 20, (float) $bar->length_cm, 0.001);
        $this->assertSame($offcut->id, $bar->warehouse_id, 'Sisa batang pindah ke Gudang Stok Sisa.');

        $this->assertEquals(3, $this->pureng->fresh()->stock);
        // Bahan: 2 × (10.000 + 15.000) + 100 cm × 500 = 100.000; afkir 3 cm × 500 = 1.500; ongkos 2 × 25.000.
        $this->assertEquals(100_000, (float) $production->material_cost);
        $this->assertEquals(1_500, (float) $production->waste_cost);
        $this->assertEquals(151_500, (float) $production->total_cost);
        $this->assertEquals(75_750, (float) $production->hpp_per_unit);

        $item = $formula->item->fresh();
        $this->assertEquals(2, (float) $item->stock);
        $this->assertEquals(75_750, (float) $item->cost_price);

        $this->get(route('admin.warehouses.show', 'stok-sisa'))->assertOk()->assertSee('530 cm');
        $this->get(route('admin.finance.index'))->assertOk();
    }

    public function test_leftovers_can_be_sent_back_to_stock_or_thrown_away_when_posting(): void
    {
        $formula = $this->formula();
        $this->buy([[$this->pipe, 1], [$this->pureng, 5], [$this->plendes, 5]]);
        $offcut = Warehouse::forType(Warehouse::OFFCUT);
        $this->post(route('admin.adjustments.store'), [
            'adjusted_at' => '2026-11-26', 'material_id' => $this->pipe->id, 'direction' => 'in', 'reason' => 'stok_awal',
            'warehouse_id' => $offcut->id, 'qty' => 1, 'length_cm' => 33, 'unit_cost' => 500,
        ])->assertSessionHasNoErrors();
        $old = PipePiece::where('initial_length_cm', 33)->firstOrFail();
        $bar = PipePiece::where('initial_length_cm', 600)->firstOrFail();
        $production = $this->draftProduction($formula, 2);

        // Daftar sisa tampil dengan saran: 3 cm dibuang, 530 cm kembali ke stok.
        $this->get(route('admin.productions.show', $production))->assertOk()
            ->assertSee('Sisa produksi')->assertSee('Kembali ke stok')->assertSee('Buang ke sampah');

        // Pilihan dibalik: sisa pendek disimpan, sisa batang dibuang.
        $this->post(route('admin.productions.post', $production), ['leftover' => [$old->id => 'stok', $bar->id => 'sampah']])
            ->assertSessionHasNoErrors()->assertSessionMissing('error');

        $old->refresh();
        $bar->refresh();
        $this->assertSame(PipePiece::AVAILABLE, $old->status);
        $this->assertSame($offcut->id, $old->warehouse_id);
        $this->assertEqualsWithDelta(3, (float) $old->length_cm, 0.001);
        $this->assertSame(PipePiece::SCRAP, $bar->status);
        // Afkir = 530 cm × Rp 500.
        $this->assertEquals(265_000, (float) $production->fresh()->waste_cost);
        $this->assertEqualsWithDelta(3, (float) $this->pipe->fresh()->stock, 0.001);
        $this->get(route('admin.productions.show', $production))->assertOk()->assertSee('Dibuang')->assertSee('Kembali ke stok');
    }

    public function test_any_component_can_be_outsourced_with_a_rate_that_flows_into_hpp_and_vendor_bills(): void
    {
        // P1 dibending vendor Rp 3.000/buah; komponen lain dikerjakan sendiri.
        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'Matic', 'motor_type' => 'Mio', 'name' => 'Mio Bending', 'is_active' => 1,
            'parts' => [['name' => 'Leheran', 'components' => [
                ['name' => 'Pureng', 'qty' => 1, 'materials' => [['material_id' => $this->pureng->id, 'qty' => 1]]],
                ['name' => 'P1', 'qty' => 1, 'work_mode' => 'vendor', 'vendor' => 'Bengkel Bending Jaya', 'process' => 'Bending', 'service_cost' => 3_000,
                    'materials' => [['material_id' => $this->pipe->id, 'qty' => 30]]],
                ['name' => 'P2', 'qty' => 1, 'materials' => [['material_id' => $this->pipe->id, 'qty' => 20]]],
            ]]],
        ])->assertSessionHasNoErrors();
        $formula = Formula::where('name', 'Mio Bending')->firstOrFail();

        // HPP perkiraan: pureng 10.000 + pipa 50 cm × 500 + jasa 3.000.
        $this->assertEquals(3_000, $formula->serviceCostPerUnit());
        $this->assertEquals(38_000, $formula->estimatedHpp());

        // Komponen tanpa vendor ditolak.
        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'Matic', 'motor_type' => 'Mio', 'name' => 'Salah', 'is_active' => 1,
            'parts' => [['name' => 'Leheran', 'components' => [['name' => 'P1', 'qty' => 1, 'work_mode' => 'vendor', 'materials' => []]]]],
        ])->assertSessionHasErrors('parts.0.components.0.vendor_service_id');

        $this->buy([[$this->pipe, 1], [$this->pureng, 5]]);
        $production = $this->draftProduction($formula, 2);
        $this->get(route('admin.productions.show', $production))->assertOk()->assertSee('Komponen dijasakan')->assertSee('Bengkel Bending Jaya');

        $this->post(route('admin.productions.post', $production))->assertSessionMissing('error');
        $production->refresh();
        $this->assertEquals(6_000, (float) $production->service_cost);
        $this->assertEquals(76_000, (float) $production->total_cost);
        $bill = MaterialPurchase::where('kind', 'jasa')->where('production_id', $production->id)->firstOrFail();
        $this->assertEquals(6_000, (float) $bill->total);
        $this->assertSame('Bengkel Bending Jaya', $bill->supplier);
        $this->assertEquals(6_000, $bill->payable());

        // Batal pembukuan → tagihan jasa ikut hilang.
        $this->post(route('admin.productions.cancel', $production))->assertSessionMissing('error');
        $this->assertModelMissing($bill);

        // Jasa tercatat di master; tarif diubah di master → HPP formula ikut.
        $service = VendorService::firstOrFail();
        $this->assertSame('Bending', $service->process);
        $this->get(route('admin.vendor-services.index'))->assertOk()->assertSee('Bengkel Bending Jaya');
        $this->put(route('admin.vendor-services.update', $service), ['vendor' => 'Bengkel Bending Jaya', 'process' => 'Bending', 'rate' => 4_000, 'unit' => 'buah', 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertEquals(39_000, $formula->fresh()->estimatedHpp());
        $this->delete(route('admin.vendor-services.destroy', $service))->assertSessionHas('error');

        // Tambah cepat dari form formula, lalu dipilih lewat id.
        $quick = $this->postJson(route('admin.vendor-services.store'), ['vendor' => 'Chrome Makmur', 'process' => 'Chrome', 'rate' => 25_000])
            ->assertOk()->assertJson(['vendor' => 'Chrome Makmur', 'process' => 'Chrome', 'rate' => 25_000])->json('id');
        $this->postJson(route('admin.vendor-services.store'), ['vendor' => 'chrome makmur', 'process' => 'chrome', 'rate' => 1])->assertUnprocessable();
        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'Matic', 'motor_type' => 'Mio', 'name' => 'Mio Chrome', 'is_active' => 1,
            'parts' => [['name' => 'Silincer', 'components' => [['name' => 'Tabung', 'qty' => 1, 'work_mode' => 'vendor', 'vendor_service_id' => $quick, 'materials' => []]]]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(25_000, Formula::where('name', 'Mio Chrome')->firstOrFail()->serviceCostPerUnit());
    }

    public function test_semi_finished_formula_done_by_a_vendor_prices_its_output_with_the_rate(): void
    {
        $this->post(route('admin.formulas.store'), [
            'output_type' => Formula::SEMI_FINISHED, 'motor_category' => 'Matic', 'name' => 'P1 Mio bending', 'is_active' => 1,
            'work_mode' => 'vendor', 'vendor' => 'Bengkel Bending Jaya', 'process' => 'Bending', 'service_cost' => 3_000,
            'parts' => [['name' => 'Bahan', 'components' => [['name' => 'P1', 'qty' => 1, 'materials' => [['material_id' => $this->pipe->id, 'qty' => 30]]]]]],
        ])->assertSessionHasNoErrors();
        $semi = Formula::where('name', 'P1 Mio bending')->firstOrFail();
        $this->assertTrue($semi->isVendorWork());
        $this->assertEquals(15_000 + 3_000, $semi->estimatedHpp());

        // Belum ada stok: formula knalpot yang memakai P1 bending ikut menghitung tarifnya.
        $this->assertEquals(18_000, $semi->outputMaterial->unitCost());

        // Draf dari rencana langsung jadi produksi vendor dengan tarifnya.
        $plan = ProductionPlan::create(['plan_number' => 'RP-T1', 'planned_for' => '2026-12-01']);
        $plan->lines()->create(['formula_id' => $semi->id, 'qty' => 10]);
        $this->post(route('admin.plans.productions', $plan))->assertSessionMissing('error');
        $draft = Production::where('formula_id', $semi->id)->firstOrFail();
        $this->assertTrue($draft->isVendor());
        $this->assertSame('Bengkel Bending Jaya', $draft->vendor);
        $this->assertEquals(3_000, (float) $draft->service_cost_per_unit);
    }

    public function test_production_cannot_be_posted_without_enough_stock(): void
    {
        $formula = $this->formula();
        $this->buy([[$this->pureng, 1], [$this->plendes, 1]]);
        $production = $this->draftProduction($formula, 2);

        $this->post(route('admin.productions.post', $production))->assertSessionHas('error');

        $this->assertFalse($production->fresh()->isPosted());
        $this->assertEquals(1, $this->pureng->fresh()->stock);
        $this->assertEquals(0, (float) $formula->item->fresh()->stock);
    }

    public function test_cancelling_a_posted_production_restores_every_stock(): void
    {
        $formula = $this->formula();
        $this->buy([[$this->pipe, 1], [$this->pureng, 2], [$this->plendes, 2]]);
        $production = $this->draftProduction($formula, 1);
        $this->post(route('admin.productions.post', $production));

        $this->post(route('admin.productions.cancel', $production))->assertSessionMissing('error');

        $this->assertFalse($production->fresh()->isPosted());
        $bar = PipePiece::firstOrFail();
        $this->assertEquals(600, (float) $bar->length_cm);
        $this->assertSame(Warehouse::forType(Warehouse::RAW)->id, $bar->warehouse_id);
        $this->assertEquals(600, (float) $this->pipe->fresh()->stock);
        $this->assertEquals(2, $this->pureng->fresh()->stock);
        $this->assertEquals(0, (float) $formula->item->fresh()->stock);
        $this->assertDatabaseCount('production_cuts', 0);
    }

    public function test_a_purchase_whose_pipe_was_already_cut_cannot_be_deleted(): void
    {
        $formula = $this->formula();
        $purchase = $this->buy([[$this->pipe, 1], [$this->pureng, 1], [$this->plendes, 1]]);
        $this->post(route('admin.productions.post', $this->draftProduction($formula, 1)));

        $this->delete(route('admin.purchases.destroy', $purchase))->assertSessionHas('error');
        $this->assertDatabaseHas('material_purchases', ['id' => $purchase->id]);
    }

    public function test_plan_needs_use_existing_stock_and_suggest_whole_bars_to_buy(): void
    {
        $formula = $this->formula();
        $this->buy([[$this->pureng, 4]]);

        $this->post(route('admin.plans.store'), [
            'planned_for' => '2026-12-01', 'title' => 'Minggu 1',
            'lines' => [['formula_id' => $formula->id, 'qty' => 13]],
        ])->assertRedirect();
        $plan = ProductionPlan::firstOrFail();

        $needs = app(ProductionPlanner::class)->forPlan($plan)['rows']->keyBy(fn ($row) => $row['material']->id);

        // 13 × 50 cm = 650 cm: 12 knalpot muat di 1 batang 600 cm, sisanya butuh batang ke-2.
        $this->assertEquals(650, $needs[$this->pipe->id]['need']);
        $this->assertSame(2, $needs[$this->pipe->id]['buy_qty']);
        $this->assertEquals(9, $needs[$this->pureng->id]['buy_qty']);
        $this->assertEquals(13, $needs[$this->plendes->id]['buy_qty']);

        $this->get(route('admin.plans.show', $plan))->assertOk()->assertSee('2 batang');
        $this->get(route('admin.purchases.create', ['rencana' => $plan->id]))->assertOk()->assertSee('Pipa SS 1,25');

        $this->post(route('admin.plans.productions', $plan))->assertRedirect();
        $this->assertSame(13.0, (float) Production::firstOrFail()->qty);
        $this->assertSame('proses', $plan->fresh()->status);
    }

    public function test_a_pipe_offcut_can_be_scrapped_and_restored_through_adjustments(): void
    {
        $this->buy([[$this->pipe, 1]]);
        $piece = PipePiece::firstOrFail();

        $this->post(route('admin.adjustments.store'), [
            'adjusted_at' => '2026-11-26', 'material_id' => $this->pipe->id, 'direction' => 'out', 'reason' => 'rusak', 'pipe_piece_id' => $piece->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(PipePiece::SCRAP, $piece->fresh()->status);
        $this->assertEquals(0, (float) $this->pipe->fresh()->stock);

        $adjustment = MaterialAdjustment::firstOrFail();
        $this->delete(route('admin.adjustments.destroy', $adjustment))->assertSessionMissing('error');
        $this->assertSame(PipePiece::AVAILABLE, $piece->fresh()->status);
        $this->assertEquals(600, (float) $this->pipe->fresh()->stock);
    }

    public function test_purchase_records_the_buyer_and_sends_each_row_to_the_chosen_warehouse(): void
    {
        $raw = Warehouse::forType(Warehouse::RAW);
        $semi = Warehouse::forType(Warehouse::SEMI);
        $offcut = Warehouse::forType(Warehouse::OFFCUT);

        $this->post(route('admin.purchases.store'), [
            'purchased_at' => '2026-11-26', 'supplier' => 'Toko Besi Jaya', 'buyer' => 'SIDIK',
            'items' => [
                // Pureng biasanya di Setengah Jadi, kali ini disimpan di Bahan Baku.
                ['material_id' => $this->pureng->id, 'warehouse_id' => $raw->id, 'qty' => 3, 'unit_price' => 10_000],
                // Potongan pipa yang dibeli langsung masuk Stok Sisa.
                ['material_id' => $this->pipe->id, 'warehouse_id' => $offcut->id, 'qty' => 1, 'bar_length_cm' => 100, 'unit_price' => 50_000],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $purchase = MaterialPurchase::with('items')->firstOrFail();
        $this->assertSame('SIDIK', $purchase->buyer);
        $this->assertSame([$raw->id, $offcut->id], $purchase->items->pluck('warehouse_id')->all());
        $this->assertEquals(3, $this->pureng->fresh()->stockIn($raw));
        $this->assertEquals(0, $this->pureng->fresh()->stockIn($semi));
        $this->assertSame($offcut->id, PipePiece::firstOrFail()->warehouse_id);

        $this->get(route('admin.purchases.show', $purchase))->assertOk()->assertSee('dibeli oleh SIDIK')->assertSee($raw->name);
        $this->get(route('admin.purchases.index', ['q' => 'SIDIK']))->assertOk()->assertSee($purchase->invoice_number);

        // Produksi tetap bisa memakai pureng meski disimpan di luar gudang asalnya.
        $this->buy([[$this->pipe, 1], [$this->plendes, 1]]);
        $production = $this->draftProduction($this->formula(), 1);
        $this->post(route('admin.productions.post', $production))->assertSessionMissing('error');
        $this->assertEquals(2, $this->pureng->fresh()->stockIn($raw));
    }

    public function test_saw_kerf_from_production_settings_is_added_to_every_cut(): void
    {
        $this->put(route('admin.production-settings.update'), ['kerf_mm' => 3])->assertSessionHasNoErrors();
        $this->buy([[$this->pipe, 1], [$this->pureng, 1], [$this->plendes, 1]]);

        $production = $this->draftProduction($this->formula(), 1);
        $this->post(route('admin.productions.post', $production))->assertSessionMissing('error');

        // P1 30 cm + P2 20 cm, masing-masing + 0,3 cm serbuk gergaji.
        $this->assertEqualsWithDelta(600 - 50.6, (float) PipePiece::firstOrFail()->length_cm, 0.001);
    }

    public function test_accessories_have_their_own_warehouse_and_formula_part(): void
    {
        $this->get(route('admin.formulas.create'))->assertOk()->assertSee('Aksesoris');

        $accessories = Warehouse::forType(Warehouse::ACCESSORY);
        $this->post(route('admin.materials.store'), [
            'name' => 'Baut L', 'warehouse_id' => $accessories->id, 'measure' => Material::UNIT, 'unit' => 'Pcs', 'last_price' => 500, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $bolt = Material::where('name', 'Baut L')->firstOrFail();
        $this->assertStringStartsWith('AK-', $bolt->code);

        $this->buy([[$bolt, 10]]);
        $this->assertEquals(10, $bolt->fresh()->stockIn($accessories));
        $this->get(route('admin.warehouses.show', 'aksesoris'))->assertOk()->assertSee('Baut L');
    }

    public function test_vendor_process_sends_materials_out_and_receives_semi_finished_goods_with_a_service_bill(): void
    {
        $this->buy([[$this->pipe, 1], [$this->plendes, 4]]);
        $this->post(route('admin.formulas.store'), [
            'output_type' => Formula::SEMI_FINISHED, 'motor_category' => 'matic', 'motor_type' => 'Mio', 'name' => 'Leheran Mio bending', 'is_active' => 1,
            'parts' => [['name' => 'Bahan', 'components' => [
                ['name' => 'P1', 'qty' => 1, 'materials' => [['material_id' => $this->pipe->id, 'qty' => 30]]],
                ['name' => 'Plendes', 'qty' => 1, 'materials' => [['material_id' => $this->plendes->id, 'qty' => 1]]],
            ]]],
        ])->assertSessionHasNoErrors();
        $formula = Formula::where('name', 'Leheran Mio bending')->firstOrFail();
        $output = $formula->outputMaterial;
        $semi = Warehouse::forType(Warehouse::SEMI);

        $this->assertNull($formula->item_id);
        $this->assertSame($semi->id, $output->warehouse_id);

        $this->post(route('admin.productions.store'), [
            'produced_at' => '2026-11-26', 'formula_id' => $formula->id, 'qty' => 2,
            'mode' => 'vendor', 'vendor' => 'Bengkel Joko', 'process' => 'Bending', 'service_cost_per_unit' => 15_000,
        ])->assertSessionHasNoErrors();
        $job = Production::latest('id')->firstOrFail();

        $this->post(route('admin.productions.post', $job))->assertSessionMissing('error');
        $job->refresh();

        // Bahan keluar saat dikirim: 2 × (30 cm × 500 + plendes 15.000) = 60.000.
        $this->assertSame('DI VENDOR', $job->statusLabel());
        $this->assertEquals(2, $this->plendes->fresh()->stock);
        $this->assertEquals(30_000, (float) $job->hpp_per_unit);
        $this->assertEquals(60_000, $job->outstandingValue());
        $this->assertEquals(0, (float) $output->fresh()->stock);
        $this->get(route('admin.warehouses.show', 'di-vendor'))->assertOk()->assertSee('Bengkel Joko');

        $this->post(route('admin.productions.receive', $job), ['received_at' => '2026-11-28', 'qty' => 1, 'service_cost_per_unit' => 15_000])
            ->assertSessionMissing('error');

        $this->assertEquals(1, $output->fresh()->stockIn($semi));
        $this->assertEquals(45_000, (float) $output->fresh()->avg_cost);
        $bill = MaterialPurchase::where('kind', 'jasa')->firstOrFail();
        $this->assertSame('Bengkel Joko', $bill->supplier);
        $this->assertEquals(15_000, $bill->payable());
        $this->delete(route('admin.purchases.destroy', $bill))->assertSessionHas('error');

        // Pembatalan ditolak selama sudah ada yang diterima.
        $this->post(route('admin.productions.cancel', $job))->assertSessionHas('error');

        $this->post(route('admin.productions.receive', $job), ['received_at' => '2026-11-29', 'qty' => 5, 'service_cost_per_unit' => 15_000])
            ->assertSessionHas('error');
        $this->post(route('admin.productions.receive', $job), ['received_at' => '2026-11-29', 'qty' => 1, 'service_cost_per_unit' => 15_000]);
        $this->assertSame('SELESAI', $job->fresh()->statusLabel());
        $this->get(route('admin.productions.show', $job))->assertOk()->assertSee($bill->invoice_number);

        // Hapus penerimaan terakhir: stok hasil & tagihannya batal.
        $this->delete(route('admin.productions.receipts.destroy', $job->receipts()->latest('id')->first()))->assertSessionMissing('error');
        $this->assertEquals(1, $output->fresh()->stock);
        $this->assertSame(1, MaterialPurchase::where('kind', 'jasa')->count());
    }

    public function test_vendor_mode_is_only_for_semi_finished_formulas_and_internal_semi_goes_to_semi_warehouse(): void
    {
        $knalpot = $this->formula();
        $this->post(route('admin.productions.store'), [
            'produced_at' => '2026-11-26', 'formula_id' => $knalpot->id, 'qty' => 1, 'mode' => 'vendor', 'vendor' => 'X',
        ])->assertSessionHasErrors('mode');

        $this->buy([[$this->plendes, 2]]);
        $this->post(route('admin.formulas.store'), [
            'output_type' => Formula::SEMI_FINISHED, 'motor_category' => 'matic', 'name' => 'Plendes las', 'is_active' => 1,
            'parts' => [['name' => 'Bahan', 'components' => [['name' => 'Plendes', 'qty' => 1, 'materials' => [['material_id' => $this->plendes->id, 'qty' => 1]]]]]],
        ])->assertSessionHasNoErrors();
        $semiFormula = Formula::where('name', 'Plendes las')->firstOrFail();

        $production = $this->draftProduction($semiFormula, 2);
        $this->post(route('admin.productions.post', $production))->assertSessionMissing('error');

        $this->assertEquals(2, $semiFormula->outputMaterial->fresh()->stockIn(Warehouse::forType(Warehouse::SEMI)));
        $this->assertEquals(0, $this->plendes->fresh()->stock);

        $this->post(route('admin.productions.cancel', $production))->assertSessionMissing('error');
        $this->assertEquals(0, (float) $semiFormula->outputMaterial->fresh()->stock);
        $this->assertEquals(2, $this->plendes->fresh()->stock);
    }

    public function test_purchase_rejects_a_warehouse_the_material_cannot_go_to(): void
    {
        $this->post(route('admin.purchases.store'), [
            'purchased_at' => '2026-11-26',
            'items' => [['material_id' => $this->pipe->id, 'warehouse_id' => Warehouse::forType(Warehouse::SEMI)->id, 'qty' => 1, 'unit_price' => 300_000]],
        ])->assertSessionHasErrors('items.0.warehouse_id');

        $this->assertDatabaseCount('material_purchases', 0);
    }

    /** Formula Mio: Leheran = pureng + plendes + P1 30 cm + P2 20 cm, ongkos 25.000. */
    private function formula(): Formula
    {
        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'matic', 'motor_type' => 'Mio', 'name' => 'Mio Racing', 'is_active' => 1,
            'parts' => [['name' => 'Leheran', 'components' => [
                ['name' => 'Pureng', 'qty' => 1, 'materials' => [['material_id' => $this->pureng->id, 'qty' => 1]]],
                ['name' => 'Plendes', 'qty' => 1, 'materials' => [['material_id' => $this->plendes->id, 'qty' => 1]]],
                ['name' => 'P1', 'qty' => 1, 'materials' => [['material_id' => $this->pipe->id, 'qty' => 30]]],
                ['name' => 'P2', 'qty' => 1, 'materials' => [['material_id' => $this->pipe->id, 'qty' => 20]]],
            ]]],
            'costs' => [['name' => 'Ongkos las', 'amount' => 25_000]],
        ])->assertSessionHasNoErrors();

        return Formula::latest('id')->firstOrFail();
    }

    /**
     * Beli bahan dengan harga master: pipa per batang, lainnya per satuan.
     *
     * @param  list<array{0: Material, 1: float}>  $rows
     */
    private function buy(array $rows): MaterialPurchase
    {
        $this->post(route('admin.purchases.store'), [
            'purchased_at' => '2026-11-25',
            'items' => collect($rows)->map(fn (array $row) => [
                'material_id' => $row[0]->id, 'qty' => $row[1], 'bar_length_cm' => $row[0]->bar_length_cm, 'unit_price' => (float) $row[0]->last_price,
            ])->all(),
        ])->assertSessionHasNoErrors();

        return MaterialPurchase::latest('id')->firstOrFail();
    }

    private function draftProduction(Formula $formula, int $qty): Production
    {
        $this->post(route('admin.productions.store'), [
            'produced_at' => '2026-11-26', 'formula_id' => $formula->id, 'qty' => $qty, 'worker' => 'SIDIK',
        ])->assertSessionHasNoErrors();

        return Production::latest('id')->firstOrFail();
    }
}
