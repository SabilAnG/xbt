<?php

namespace App\Services;

use App\Models\Formula;
use App\Models\Item;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\MaterialPurchase;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Vendor;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pembukuan stok untuk panel admin, mengikuti alur buku kerja knalpot:
 *
 * - Produksi: stok knalpot jadi bertambah, bahan berkurang sesuai Resep BOM
 *   (baris resep khusus merek hanya dipakai untuk produk merek itu).
 * - Penjualan: stok knalpot berkurang; qty minus berarti retur.
 * - Pembelian bahan: stok bahan bertambah.
 * - Penyesuaian bahan: koreksi plus/minus hasil hitung fisik.
 *
 * Stok boleh minus, sama seperti di buku kerja — angka minus adalah tanda ada
 * pembelian yang belum dicatat, bukan alasan menolak input produksi.
 *
 * Di Excel pemakaian bahan dan HPP penjualan dihitung LIVE dari resep & HPP
 * produk saat ini. Supaya angkanya tetap sama, setiap kali resep atau produk
 * diubah, syncItem()/syncFormula() menghitung ulang produksi & penjualan lama.
 */
class InventoryService
{
    /**
     * @param  array{worker?: ?string, notes?: ?string}  $extra
     */
    public function recordProduction(Item $item, float $qty, CarbonInterface|string $date, array $extra = []): Production
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty produksi harus lebih dari 0.');
        }

        if (! $item->formula_id) {
            throw new InvalidArgumentException("Produk \"{$item->name}\" belum punya Resep BOM.");
        }

        $date = Carbon::parse($date);

        return DB::transaction(function () use ($item, $qty, $date, $extra) {
            $production = Production::create([
                'production_number' => DocumentNumber::next('productions', $date),
                'produced_at' => $date->toDateString(),
                'formula_id' => $item->formula_id,
                'item_id' => $item->id,
                'worker' => $extra['worker'] ?? null,
                'batch_qty' => $qty,
                'output_qty' => $qty,
                'status' => 'posted',
                'posted_at' => now(),
                'notes' => $extra['notes'] ?? null,
            ]);

            $this->consumeMaterials($production, $item);
            $this->moveItem($item, $qty, 0, (float) $item->cost_price, 'production', $production, $date, $production->production_number);

            return $production;
        });
    }

    /**
     * Hitung ulang semua produksi & penjualan satu produk dari resep, merek,
     * dan HPP-nya yang sekarang — padanan Excel yang selalu menghitung live.
     */
    public function syncItem(Item $item): void
    {
        DB::transaction(function () use ($item) {
            $item->refresh();
            $touched = collect();

            foreach (Production::where('item_id', $item->id)->get() as $production) {
                $touched = $touched->merge($this->clearMaterials($production));

                if ($item->formula_id) {
                    $production->forceFill(['formula_id' => $item->formula_id])->save();
                }

                $this->consumeMaterials($production, $item, recalculate: false);
                $touched = $touched->merge($production->materials()->pluck('material_id'));

                StockMovement::where('source_type', Production::class)->where('source_id', $production->id)
                    ->update(['unit_cost' => $item->cost_price]);
            }

            foreach (SaleItem::where('item_id', $item->id)->with('sale')->get() as $line) {
                $line->forceFill(['unit_cost' => $item->cost_price])->save();
                $line->sale?->forceFill(['total_cost' => (float) $line->qty * (float) $item->cost_price])->save();
            }

            Material::whereIn('id', $touched->unique())->get()->each->recalculateStock();
            $item->recalculateStock();
        });
    }

    /** Resep diubah: hitung ulang semua produk yang memakai resep ini. */
    public function syncFormula(Formula $formula): void
    {
        Item::where('formula_id', $formula->id)->get()->each(fn (Item $item) => $this->syncItem($item));
    }

    public function deleteProduction(Production $production): void
    {
        DB::transaction(function () use ($production) {
            $this->forgetMovements($production);
            $production->materials()->delete();
            $production->delete();
        });
    }

    /**
     * @param  array{channel?: ?string, customer_name?: ?string, paid_amount?: float|int|string|null, notes?: ?string}  $extra
     */
    public function recordSale(Item $item, float $qty, float $unitPrice, CarbonInterface|string $date, array $extra = []): Sale
    {
        if ($qty == 0) {
            throw new InvalidArgumentException('Qty penjualan tidak boleh 0.');
        }

        $date = Carbon::parse($date);

        return DB::transaction(function () use ($item, $qty, $unitPrice, $date, $extra) {
            $hpp = (float) $item->cost_price;
            $total = $qty * $unitPrice;

            $sale = Sale::create([
                'invoice_number' => DocumentNumber::next('sales', $date),
                'sold_at' => $date->toDateString(),
                'customer_name' => $extra['customer_name'] ?? null,
                'channel' => $extra['channel'] ?? null,
                'wallet_id' => $extra['wallet_id'] ?? null,
                'subtotal' => $total,
                'total' => $total,
                'total_cost' => $hpp * $qty,
                'paid_amount' => (float) ($extra['paid_amount'] ?? 0),
                'status' => 'posted',
                'posted_at' => now(),
                'notes' => $extra['notes'] ?? null,
            ]);

            $sale->items()->create([
                'item_id' => $item->id,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'unit_cost' => $hpp,
            ]);

            // Qty minus = retur: barang kembali masuk gudang.
            $this->moveItem($item, max(-$qty, 0), max($qty, 0), $hpp, 'sale', $sale, $date, $sale->invoice_number);

            return $sale;
        });
    }

    public function deleteSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            app(WalletService::class)->forget($sale);
            $this->forgetMovements($sale);
            $sale->items()->delete();
            $sale->delete();
        });
    }

    /**
     * @param  array{supplier?: ?string, recorded_in_expenses?: bool, paid_from?: ?string, notes?: ?string}  $extra
     */
    public function recordMaterialPurchase(Material $material, float $qty, float $unitPrice, CarbonInterface|string $date, array $extra = []): MaterialPurchase
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty pembelian harus lebih dari 0.');
        }

        $date = Carbon::parse($date);

        return DB::transaction(function () use ($material, $qty, $unitPrice, $date, $extra) {
            $supplier = trim((string) ($extra['supplier'] ?? ''));
            $total = $qty * $unitPrice;

            $purchase = MaterialPurchase::create([
                'invoice_number' => DocumentNumber::next('material_purchases', $date),
                'purchased_at' => $date->toDateString(),
                'vendor_id' => $supplier !== '' ? Vendor::firstOrCreate(['name' => $supplier])->id : null,
                'subtotal' => $total,
                'total' => $total,
                'recorded_in_expenses' => (bool) ($extra['recorded_in_expenses'] ?? false),
                'wallet_id' => $extra['wallet_id'] ?? null,
                'paid_from' => $extra['paid_from'] ?? null,
                'status' => 'posted',
                'posted_at' => now(),
                'notes' => $extra['notes'] ?? null,
            ]);

            $purchase->items()->create([
                'material_id' => $material->id,
                'qty' => $qty,
                'unit_cost' => $unitPrice,
                'subtotal' => $total,
            ]);

            $this->moveMaterial($material, $qty, 0, $unitPrice, 'purchase', $purchase, $date, $purchase->invoice_number);
            $this->refreshAverageCost($material);

            return $purchase;
        });
    }

    public function deleteMaterialPurchase(MaterialPurchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            app(WalletService::class)->forget($purchase);
            $materials = $purchase->items()->with('material')->get()->pluck('material');

            $this->forgetMovements($purchase);
            $purchase->items()->delete();
            $purchase->delete();

            $materials->filter()->each(fn (Material $material) => $this->refreshAverageCost($material));
        });
    }

    /** Koreksi stok bahan: plus untuk temuan, minus untuk rusak/hilang. */
    public function adjustMaterial(Material $material, float $qty, CarbonInterface|string $date, ?string $reason = null): MaterialMovement
    {
        if ($qty == 0) {
            throw new InvalidArgumentException('Qty penyesuaian tidak boleh 0.');
        }

        return DB::transaction(fn () => $this->moveMaterial(
            $material,
            max($qty, 0),
            max(-$qty, 0),
            (float) $material->cost_price,
            'adjustment',
            null,
            Carbon::parse($date),
            $reason,
        ));
    }

    public function deleteAdjustment(MaterialMovement $movement): void
    {
        if ($movement->type !== 'adjustment') {
            throw new InvalidArgumentException('Hanya baris penyesuaian yang bisa dihapus di sini.');
        }

        DB::transaction(function () use ($movement) {
            $material = $movement->material;
            $movement->delete();
            $material?->recalculateStock();
        });
    }

    /** Harga pokok bahan = rata-rata tertimbang semua pembelian. */
    public function refreshAverageCost(Material $material): void
    {
        $totals = DB::table('material_purchase_items')
            ->where('material_id', $material->id)
            ->selectRaw('COALESCE(SUM(qty), 0) AS qty, COALESCE(SUM(subtotal), 0) AS total')
            ->first();

        if ((float) $totals->qty > 0) {
            $material->forceFill(['cost_price' => round((float) $totals->total / (float) $totals->qty, 2)])->save();
        }
    }

    private function moveItem(Item $item, float $in, float $out, float $unitCost, string $type, object $source, CarbonInterface $date, ?string $notes): StockMovement
    {
        $movement = StockMovement::create([
            'item_id' => $item->id,
            'type' => $type,
            'qty_in' => $in,
            'qty_out' => $out,
            'unit_cost' => $unitCost,
            'source_type' => $source::class,
            'source_id' => $source->getKey(),
            'moved_at' => $date,
            'notes' => $notes,
        ]);

        $item->recalculateStock();

        return $movement->refresh();
    }

    private function moveMaterial(Material $material, float $in, float $out, float $unitCost, string $type, ?object $source, CarbonInterface $date, ?string $notes): MaterialMovement
    {
        $movement = MaterialMovement::create([
            'material_id' => $material->id,
            'type' => $type,
            'qty_in' => $in,
            'qty_out' => $out,
            'unit_cost' => $unitCost,
            'source_type' => $source ? $source::class : null,
            'source_id' => $source?->getKey(),
            'moved_at' => $date,
            'notes' => $notes,
        ]);

        $material->recalculateStock();

        return $movement->refresh();
    }

    /**
     * Catat bahan terpakai satu produksi menurut resep & merek produk saat ini.
     */
    private function consumeMaterials(Production $production, Item $item, bool $recalculate = true): void
    {
        $formula = Formula::with('materials.material')->find($item->formula_id);
        $qty = (float) $production->output_qty;
        $hpp = (float) $item->cost_price;
        $materialCost = 0.0;

        foreach ($formula?->materials ?? [] as $line) {
            if (! $line->appliesToBrand($item->brand)) {
                continue;
            }

            $need = (float) $line->qty * $qty;
            $unitCost = (float) $line->material->cost_price;
            $materialCost += $need * $unitCost;

            $production->materials()->create([
                'material_id' => $line->material_id,
                'qty' => $need,
                'unit_cost' => $unitCost,
                'subtotal' => $need * $unitCost,
            ]);

            MaterialMovement::create([
                'material_id' => $line->material_id,
                'type' => 'production',
                'qty_out' => $need,
                'unit_cost' => $unitCost,
                'source_type' => Production::class,
                'source_id' => $production->id,
                'moved_at' => $production->produced_at,
                'notes' => $production->production_number,
            ]);

            if ($recalculate) {
                $line->material->recalculateStock();
            }
        }

        $production->forceFill([
            'material_cost' => $materialCost,
            'hpp_per_unit' => $hpp,
            'total_cost' => $hpp * $qty,
        ])->save();
    }

    /**
     * @return Collection<int, int> id bahan yang terdampak
     */
    private function clearMaterials(Production $production): Collection
    {
        $ids = $production->materials()->pluck('material_id');
        $production->materials()->delete();
        MaterialMovement::where('source_type', Production::class)->where('source_id', $production->id)->delete();

        return $ids;
    }

    /** Hapus kartu stok milik satu dokumen lalu hitung ulang stok yang terdampak. */
    private function forgetMovements(object $source): void
    {
        $itemIds = StockMovement::where('source_type', $source::class)->where('source_id', $source->getKey())->pluck('item_id');
        $materialIds = MaterialMovement::where('source_type', $source::class)->where('source_id', $source->getKey())->pluck('material_id');

        StockMovement::where('source_type', $source::class)->where('source_id', $source->getKey())->delete();
        MaterialMovement::where('source_type', $source::class)->where('source_id', $source->getKey())->delete();

        Item::whereIn('id', $itemIds->unique())->get()->each->recalculateStock();
        Material::whereIn('id', $materialIds->unique())->get()->each->recalculateStock();
    }
}
