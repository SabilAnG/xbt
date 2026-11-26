<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\PipePiece;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pindah Barang antar gudang: lokasi berubah, total stok & nilai tetap. */
class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_units_and_pipe_pieces_move_between_warehouses_and_can_be_moved_back(): void
    {
        $raw = Warehouse::forType(Warehouse::RAW);
        $semi = Warehouse::forType(Warehouse::SEMI);
        $offcut = Warehouse::forType(Warehouse::OFFCUT);
        $bolt = Material::factory()->create(['name' => 'Baut M6']);
        $pipe = Material::factory()->pipe()->create(['name' => 'Pipa SS']);

        $this->post(route('admin.purchases.store'), [
            'purchased_at' => '2026-12-01', 'supplier' => 'Toko Besi Jaya',
            'items' => [
                ['material_id' => $bolt->id, 'qty' => 100, 'unit_price' => 500],
                ['material_id' => $pipe->id, 'qty' => 2, 'bar_length_cm' => 600, 'unit_price' => 300_000],
            ],
        ])->assertSessionHasNoErrors();
        $piece = $pipe->pieces()->firstOrFail();

        $this->get(route('admin.transfers.create'))->assertOk()->assertSee('Baut M6')->assertSee($piece->code);
        $this->post(route('admin.transfers.store'), [
            'transferred_at' => '2026-12-02', 'from_warehouse_id' => $raw->id, 'to_warehouse_id' => $semi->id, 'moved_by' => 'ANDI',
            'items' => [['material_id' => $bolt->id, 'qty' => 30]],
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.transfers.store'), [
            'transferred_at' => '2026-12-02', 'from_warehouse_id' => $raw->id, 'to_warehouse_id' => $offcut->id,
            'pieces' => [$piece->id],
        ])->assertSessionHasNoErrors();

        $bolt = $bolt->fresh()->load('stocks');
        $this->assertEquals(70, $bolt->stockIn($raw));
        $this->assertEquals(30, $bolt->stockIn($semi));
        $this->assertEquals(100, $bolt->stock);
        $this->assertEquals(500, (float) $bolt->avg_cost, 'Harga rata-rata tidak berubah.');
        $this->assertSame($offcut->id, $piece->fresh()->warehouse_id);
        $this->assertEquals(600, $pipe->fresh()->load('stocks')->stockIn($offcut));
        $this->assertEquals(1_200, $pipe->fresh()->stock);

        // Lebih dari stok, gudang sama, dan pipa ke gudang yang tidak boleh: ditolak.
        $this->post(route('admin.transfers.store'), [
            'transferred_at' => '2026-12-03', 'from_warehouse_id' => $raw->id, 'to_warehouse_id' => $semi->id,
            'items' => [['material_id' => $bolt->id, 'qty' => 71]],
        ])->assertSessionHas('error');
        $this->post(route('admin.transfers.store'), [
            'transferred_at' => '2026-12-03', 'from_warehouse_id' => $raw->id, 'to_warehouse_id' => $raw->id,
            'items' => [['material_id' => $bolt->id, 'qty' => 1]],
        ])->assertSessionHasErrors('to_warehouse_id');
        $other = $pipe->pieces()->where('id', '!=', $piece->id)->firstOrFail();
        $this->post(route('admin.transfers.store'), [
            'transferred_at' => '2026-12-03', 'from_warehouse_id' => $raw->id, 'to_warehouse_id' => $semi->id, 'pieces' => [$other->id],
        ])->assertSessionHas('error');
        $this->assertSame(2, StockTransfer::count());

        // Dibatalkan: barang kembali ke gudang asal.
        foreach (StockTransfer::all() as $transfer) {
            $this->delete(route('admin.transfers.destroy', $transfer))->assertSessionHasNoErrors();
        }

        $this->assertEquals(100, $bolt->fresh()->load('stocks')->stockIn($raw));
        $this->assertSame($raw->id, $piece->fresh()->warehouse_id);
        $this->assertSame(PipePiece::AVAILABLE, $piece->fresh()->status);
    }

    public function test_transfer_cannot_be_undone_once_moved_stock_is_used(): void
    {
        $raw = Warehouse::forType(Warehouse::RAW);
        $semi = Warehouse::forType(Warehouse::SEMI);
        $bolt = Material::factory()->create();

        $this->post(route('admin.purchases.store'), [
            'purchased_at' => '2026-12-01', 'items' => [['material_id' => $bolt->id, 'qty' => 10, 'unit_price' => 500]],
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.transfers.store'), [
            'transferred_at' => '2026-12-02', 'from_warehouse_id' => $raw->id, 'to_warehouse_id' => $semi->id,
            'items' => [['material_id' => $bolt->id, 'qty' => 10]],
        ])->assertSessionHasNoErrors();

        // Sebagian stok di gudang tujuan terpakai produksi.
        app(StockService::class)->issueUnits($bolt->fresh(), $semi, 4, 'pemakaian', null, '2026-12-03');

        $this->delete(route('admin.transfers.destroy', StockTransfer::firstOrFail()))->assertSessionHas('error');
        $this->assertSame(1, StockTransfer::count());
        $this->assertEquals(6, $bolt->fresh()->load('stocks')->stockIn($semi));
    }
}
