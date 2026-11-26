<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Stok produk jadi (Gudang Finish Good) dan penjualannya.
 *
 * HPP produk = rata-rata tertimbang HPP produksi yang masuk. Penjualan
 * membekukan HPP saat itu ke baris nota, sehingga laba historis tetap.
 */
class InventoryService
{
    /** Knalpot hasil produksi masuk Finish Good; HPP produk dirata-rata. */
    public function receiveProduction(Production $production): void
    {
        $item = Item::lockForUpdate()->findOrFail($production->item_id);
        $qty = (float) $production->qty;
        $hpp = (float) $production->hpp_per_unit;
        $stock = max((float) $item->stock, 0);
        $cost = $stock + $qty > 0 ? (($stock * (float) $item->cost_price) + ($qty * $hpp)) / ($stock + $qty) : $hpp;

        $item->forceFill(['cost_price' => round($cost, 2)])->save();
        $this->moveItem($item, $qty, 0, $hpp, 'production', $production, $production->produced_at, $production->production_number);
    }

    /** Kebalikan receiveProduction(): stok & HPP rata-rata dikembalikan. */
    public function revertProduction(Production $production): void
    {
        $item = Item::lockForUpdate()->findOrFail($production->item_id);
        $qty = (float) $production->qty;

        if ((float) $item->stock + 0.0001 < $qty) {
            throw new RuntimeException("Stok {$item->name} tinggal ".qty((float) $item->stock).'; sebagian hasil produksi ini sudah terjual.');
        }

        $remaining = (float) $item->stock - $qty;

        if ($remaining > 0) {
            $cost = (((float) $item->stock * (float) $item->cost_price) - ($qty * (float) $production->hpp_per_unit)) / $remaining;
            $item->forceFill(['cost_price' => round(max($cost, 0), 2)])->save();
        }

        StockMovement::where('source_type', $production->getMorphClass())->where('source_id', $production->id)->delete();
        $item->recalculateStock();
    }

    /** Hitung ulang stok produk setelah stok awalnya diubah. */
    public function syncItem(Item $item): void
    {
        $item->recalculateStock();
    }

    /**
     * @param  array{channel?: ?string, customer_name?: ?string, wallet_id?: ?int, paid_amount?: float|int|string|null, notes?: ?string}  $extra
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
            $itemIds = StockMovement::where('source_type', $sale->getMorphClass())->where('source_id', $sale->id)->pluck('item_id');
            StockMovement::where('source_type', $sale->getMorphClass())->where('source_id', $sale->id)->delete();
            $sale->items()->delete();
            $sale->delete();
            Item::whereIn('id', $itemIds->unique())->get()->each->recalculateStock();
        });
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
}
