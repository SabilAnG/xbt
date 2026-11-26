<?php

namespace App\Services;

use App\Models\Formula;
use App\Models\Material;
use App\Models\MaterialPurchaseItem;
use App\Models\PipePiece;
use App\Models\ProductionPlan;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Menghitung kebutuhan bahan untuk satu atau beberapa formula, membandingkan
 * dengan stok gudang, dan menerjemahkan kekurangannya ke satuan beli
 * (pipa: jumlah batang baru setelah sisa potongan yang ada dipakai dulu).
 */
class ProductionPlanner
{
    public function __construct(private CuttingPlanner $cutting) {}

    /**
     * Kebutuhan sebuah rencana produksi.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, buy_total: float, estimated_cost: float, lines: Collection<int, array<string, mixed>>}
     */
    public function forPlan(ProductionPlan $plan): array
    {
        $plan->loadMissing('lines.formula');

        return $this->forFormulas($plan->lines->map(fn ($line) => [$line->formula, (float) $line->qty])->all());
    }

    /**
     * @param  list<array{0: Formula, 1: float}>  $targets
     * @return array{rows: Collection<int, array<string, mixed>>, buy_total: float, estimated_cost: float, lines: Collection<int, array<string, mixed>>}
     */
    public function forFormulas(array $targets): array
    {
        $requirements = collect();
        $lines = collect();
        $planned = [];

        foreach ($targets as [$formula, $units]) {
            // Barang setengah jadi yang ikut dibuat di rencana ini dihitung sebagai stok yang akan datang.
            if ($formula->isSemiFinished() && $formula->output_material_id) {
                $planned[$formula->output_material_id] = ($planned[$formula->output_material_id] ?? 0) + $units;
            }

            $formulaLines = $formula->requirements($units)->map(fn (array $line) => $line + ['formula' => $formula->name]);
            $requirements = $requirements->merge($formulaLines);
            $lines->push([
                'formula' => $formula,
                'qty' => $units,
                'hpp' => $formula->estimatedHpp(),
                'cost' => $formula->estimatedHpp() * $units,
            ]);
        }

        $madeBy = Formula::query()->whereNotNull('output_material_id')->pluck('name', 'output_material_id');

        $rows = $requirements->groupBy(fn (array $line) => $line['material']->id)->map(function (Collection $group) use ($planned, $madeBy) {
            /** @var Material $material */
            $material = $group->first()['material'];

            return $material->isPipe()
                ? $this->pipeRow($material, $group)
                : $this->unitRow($material, $group, (float) ($planned[$material->id] ?? 0), $madeBy[$material->id] ?? null);
        })->sortBy(fn (array $row) => [$row['material']->warehouse_id, $row['material']->name])->values();

        return [
            'rows' => $rows,
            'buy_total' => (float) $rows->sum('buy_cost'),
            'estimated_cost' => (float) $lines->sum('cost'),
            'lines' => $lines,
        ];
    }

    /**
     * Rencana potong satu bahan pipa dari potongan yang tersedia.
     *
     * @param  list<array{length: float, label: string}>  $cuts
     * @return array{assignments: list<array<string, mixed>>, pieces: list<array<string, mixed>>, new_bars: int, unplaced: list<array{length: float, label: string}>}
     */
    public function cuttingPlan(Material $material, array $cuts, bool $allowNewBars): array
    {
        $offcut = Warehouse::forType(Warehouse::OFFCUT)->id;
        $pieces = PipePiece::where('material_id', $material->id)->where('status', PipePiece::AVAILABLE)->orderBy('id')->get()
            ->map(fn (PipePiece $piece) => [
                'key' => $piece->id,
                'code' => $piece->code,
                'length' => (float) $piece->length_cm,
                'offcut' => $piece->warehouse_id === $offcut,
            ])->all();

        return $this->cutting->plan($cuts, $pieces, Material::kerfCm(), $allowNewBars ? (float) $material->bar_length_cm : 0);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $group
     * @return array<string, mixed>
     */
    private function unitRow(Material $material, Collection $group, float $planned = 0, ?string $madeBy = null): array
    {
        $material->loadMissing('stocks', 'warehouse');
        $need = (float) $group->sum('qty');
        $available = (float) $material->stock;
        $onOrder = $this->onOrder($material);
        $short = max($need - $available - $planned - $onOrder, 0);
        // Barang hasil formula dibuat, bukan dibeli.
        $buy = $madeBy ? 0 : $this->buyQty($material, $short);

        return [
            'material' => $material,
            'need' => $need,
            'available' => $available,
            'planned' => $planned,
            'on_order' => $onOrder,
            'made_by' => $madeBy,
            'make_qty' => $madeBy ? $short : 0,
            'short' => $short,
            'buy_qty' => $buy,
            'buy_unit' => $material->purchaseUnit(),
            'buy_cost' => $buy * $material->purchaseUnitCost(),
            'cost' => $need * $material->unitCost(),
            'uses' => $group->map(fn (array $line) => $line['part'].' · '.$line['component'])->unique()->values()->all(),
            'cutting' => null,
        ];
    }

    /** Sisa pesanan pembelian yang belum diterima, dalam satuan pakai (atau satuan beli). */
    private function onOrder(Material $material, bool $inPurchaseUnit = false): float
    {
        $qty = (float) MaterialPurchaseItem::query()->where('material_id', $material->id)
            ->selectRaw('COALESCE(SUM(qty - received_qty - cancelled_qty), 0) as outstanding')->value('outstanding');

        return $inPurchaseUnit ? $qty : $material->toStockQty($qty);
    }

    /** Kekurangan (satuan pakai) dijadikan jumlah beli; dengan konversi dibulatkan ke atas, mis. 1,2 lembar → 2 lembar. */
    private function buyQty(Material $material, float $short): float
    {
        if ($short <= 0) {
            return 0;
        }

        return $material->hasConversion() ? ceil(round($short / $material->conversionFactor(), 6)) : ceil($short * 1000) / 1000;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $group
     * @return array<string, mixed>
     */
    private function pipeRow(Material $material, Collection $group): array
    {
        $cuts = $group->flatMap(fn (array $line) => collect($line['cuts'])->map(fn (float $length) => [
            'length' => $length, 'label' => $line['part'].' · '.$line['component'],
        ]))->values()->all();
        $plan = $this->cuttingPlan($material, $cuts, allowNewBars: true);
        $need = (float) $group->sum('qty');
        // Batang yang sudah dipesan tapi belum datang mengurangi yang perlu dibeli.
        $onOrderBars = $this->onOrder($material, inPurchaseUnit: true);
        $buyBars = max($plan['new_bars'] - (int) floor($onOrderBars), 0);

        return [
            'material' => $material,
            'need' => $need,
            'available' => (float) $material->stock,
            'planned' => 0,
            'on_order' => $onOrderBars * (float) $material->bar_length_cm,
            'made_by' => null,
            'make_qty' => 0,
            'short' => $plan['new_bars'] > 0 || $plan['unplaced'] ? max($need - (float) $material->stock, 0) : 0,
            'buy_qty' => $buyBars,
            'buy_unit' => 'batang',
            'buy_cost' => $buyBars * $material->purchaseUnitCost(),
            'cost' => $need * $material->unitCost(),
            'uses' => $group->map(fn (array $line) => $line['part'].' · '.$line['component'])->unique()->values()->all(),
            'cutting' => $plan,
        ];
    }
}
