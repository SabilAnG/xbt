<?php

namespace Tests\Feature;

use App\Models\Formula;
use App\Models\Item;
use App\Models\Material;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private Material $pipe;

    private Material $shieldFd;

    private Material $shieldXbt;

    private Formula $formula;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventory = app(InventoryService::class);
        $this->pipe = Material::create(['sku' => 'B1', 'name' => 'Pipa', 'unit' => 'Pcs', 'dimension_type' => 'count']);
        $this->shieldFd = Material::create(['sku' => 'B2', 'name' => 'Tameng FD', 'unit' => 'Pcs', 'dimension_type' => 'count']);
        $this->shieldXbt = Material::create(['sku' => 'B3', 'name' => 'Tameng XBT', 'unit' => 'Pcs', 'dimension_type' => 'count']);

        $this->formula = Formula::create(['code' => 'MIO', 'name' => 'MIO']);
        $this->formula->materials()->create(['material_id' => $this->pipe->id, 'input_mode' => 'direct', 'qty' => 2]);
        $this->formula->materials()->create(['material_id' => $this->shieldFd->id, 'brand' => 'FD', 'input_mode' => 'direct', 'qty' => 1]);
        $this->formula->materials()->create(['material_id' => $this->shieldXbt->id, 'brand' => 'XBT', 'input_mode' => 'direct', 'qty' => 1]);
    }

    private function item(string $brand): Item
    {
        return Item::create([
            'sku' => 'K-'.$brand, 'name' => 'Mio '.$brand, 'brand' => $brand,
            'formula_id' => $this->formula->id, 'cost_price' => 400_000, 'min_stock' => 2,
        ]);
    }

    public function test_production_adds_goods_and_consumes_only_lines_for_its_brand(): void
    {
        $this->inventory->recordMaterialPurchase($this->pipe, 10, 5_000, '2026-10-01');
        $item = $this->item('FD');

        $this->inventory->recordProduction($item, 3, '2026-10-02');

        $this->assertEquals(3, (float) $item->fresh()->stock);
        $this->assertEquals(4, (float) $this->pipe->fresh()->stock);
        $this->assertEquals(-3, (float) $this->shieldFd->fresh()->stock);
        $this->assertEquals(0, (float) $this->shieldXbt->fresh()->stock);
    }

    public function test_deleting_a_production_restores_both_stocks(): void
    {
        $item = $this->item('XBT');
        $production = $this->inventory->recordProduction($item, 2, '2026-10-02');

        $this->inventory->deleteProduction($production);

        $this->assertEquals(0, (float) $item->fresh()->stock);
        $this->assertEquals(0, (float) $this->shieldXbt->fresh()->stock);
        $this->assertDatabaseCount('material_movements', 0);
    }

    public function test_sale_reduces_stock_and_negative_qty_is_a_return(): void
    {
        $item = $this->item('FD');
        $this->inventory->recordProduction($item, 5, '2026-10-02');

        $sale = $this->inventory->recordSale($item, 2, 700_000, '2026-10-03', ['paid_amount' => 1_000_000]);
        $this->inventory->recordSale($item->fresh(), -1, 700_000, '2026-10-04');

        $this->assertEquals(4, (float) $item->fresh()->stock);
        $this->assertEquals(400_000, $sale->receivable());
        $this->assertEquals(800_000, (float) $sale->total_cost);
    }

    public function test_purchase_updates_weighted_average_cost(): void
    {
        $this->inventory->recordMaterialPurchase($this->pipe, 10, 1_000, '2026-10-01');
        $this->inventory->recordMaterialPurchase($this->pipe, 30, 2_000, '2026-10-02');

        $this->assertEquals(1_750, (float) $this->pipe->fresh()->cost_price);
        $this->assertEquals(40, (float) $this->pipe->fresh()->stock);
    }

    public function test_product_without_brand_consumes_no_material_like_the_workbook(): void
    {
        $item = Item::create(['sku' => 'K-X', 'name' => 'PCX', 'brand' => null, 'formula_id' => $this->formula->id]);

        $this->inventory->recordProduction($item, 1, '2026-10-02');

        $this->assertEquals(1, (float) $item->fresh()->stock);
        $this->assertEquals(0, (float) $this->pipe->fresh()->stock);
    }

    public function test_changing_the_recipe_recalculates_past_usage(): void
    {
        $item = $this->item('FD');
        $this->inventory->recordProduction($item, 3, '2026-10-02');

        $this->formula->materials()->where('material_id', $this->pipe->id)->update(['qty' => 5]);
        $this->inventory->syncFormula($this->formula);

        $this->assertEquals(-15, (float) $this->pipe->fresh()->stock);
    }

    public function test_changing_hpp_updates_cost_of_past_sales(): void
    {
        $item = $this->item('FD');
        $this->inventory->recordProduction($item, 2, '2026-10-02');
        $sale = $this->inventory->recordSale($item->fresh(), 2, 700_000, '2026-10-03');

        $item->update(['cost_price' => 450_000]);
        $this->inventory->syncItem($item);

        $this->assertEquals(900_000, (float) $sale->fresh()->total_cost);
    }

    public function test_opening_stock_is_part_of_the_remaining_stock(): void
    {
        $item = $this->item('FD');
        $item->update(['opening_stock' => 4]);
        $this->inventory->recordProduction($item->fresh(), 1, '2026-10-02');

        $this->assertEquals(5, (float) $item->fresh()->stock);
    }

    public function test_production_without_recipe_is_rejected(): void
    {
        $item = Item::create(['sku' => 'X', 'name' => 'Tanpa resep']);

        $this->expectException(InvalidArgumentException::class);

        $this->inventory->recordProduction($item, 1, '2026-10-02');
    }
}
