<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemAdjustment;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SalePayment;
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

    /** Satu produk per nota (dipakai pemanggil lama). */
    public function recordSale(Item $item, float $qty, float $unitPrice, CarbonInterface|string $date, array $extra = []): Sale
    {
        return $this->createSale([['item_id' => $item->id, 'qty' => $qty, 'unit_price' => $unitPrice]], $date, $extra);
    }

    /**
     * Nota penjualan berisi beberapa produk. Qty minus = retur (barang masuk
     * lagi). HPP tiap baris dibekukan dari HPP rata-rata produk saat itu.
     *
     * @param  array<int, array{item_id: int, qty: float|int|string, unit_price: float|int|string}>  $lines
     * @param  array{channel?: ?string, customer_name?: ?string, customer_phone?: ?string, discount?: float|int|string|null, notes?: ?string}  $extra
     */
    public function createSale(array $lines, CarbonInterface|string $date, array $extra = []): Sale
    {
        $lines = collect($lines)->filter(fn (array $line) => (float) $line['qty'] != 0)->values();

        if ($lines->isEmpty()) {
            throw new InvalidArgumentException('Isi minimal satu produk dengan qty selain 0.');
        }

        $date = Carbon::parse($date);

        return DB::transaction(function () use ($lines, $date, $extra) {
            $items = Item::lockForUpdate()->whereIn('id', $lines->pluck('item_id'))->get()->keyBy('id');

            // Stok cukup untuk semua baris produk yang sama sekaligus.
            foreach ($lines->groupBy('item_id') as $itemId => $rows) {
                $item = $items[$itemId];
                $sold = (float) $rows->sum(fn (array $row) => (float) $row['qty']);

                if ($sold > (float) $item->stock + 0.0001) {
                    throw new InvalidArgumentException('Stok '.$item->name.' hanya '.qty((float) $item->stock).'. Produksi dulu atau kurangi qty.');
                }
            }

            $subtotal = (float) $lines->sum(fn (array $line) => (float) $line['qty'] * (float) $line['unit_price']);
            $discount = (float) ($extra['discount'] ?? 0);

            $sale = Sale::create([
                'invoice_number' => DocumentNumber::next('sales', $date),
                'sold_at' => $date->toDateString(),
                'customer_name' => $extra['customer_name'] ?? null,
                'customer_phone' => $extra['customer_phone'] ?? null,
                'channel' => $extra['channel'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $subtotal - $discount,
                'total_cost' => (float) $lines->sum(fn (array $line) => (float) $line['qty'] * (float) $items[$line['item_id']]->cost_price),
                'paid_amount' => 0,
                'status' => 'posted',
                'posted_at' => now(),
                'notes' => $extra['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $item = $items[$line['item_id']];
                $qty = (float) $line['qty'];
                $hpp = (float) $item->cost_price;

                $sale->items()->create([
                    'item_id' => $item->id,
                    'qty' => $qty,
                    'unit_price' => (float) $line['unit_price'],
                    'unit_cost' => $hpp,
                    'subtotal' => $qty * (float) $line['unit_price'],
                ]);

                // Qty minus = retur: barang kembali masuk gudang.
                $this->moveItem($item, max(-$qty, 0), max($qty, 0), $hpp, 'sale', $sale, $date, $sale->invoice_number);
            }

            return $sale;
        });
    }

    /** Uang masuk (DP/cicilan/pelunasan) ke dompet yang dipilih. */
    public function paySale(Sale $sale, float $amount, ?int $walletId, CarbonInterface|string $date, ?string $notes = null): SalePayment
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran harus lebih dari 0.');
        }

        return DB::transaction(function () use ($sale, $amount, $walletId, $date, $notes) {
            // Kunci nota dulu: dua kiriman bersamaan (klik ganda) tidak boleh sama-sama lolos cek piutang.
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);

            if ($amount > $sale->receivable() + 0.01) {
                throw new InvalidArgumentException($sale->receivable() > 0 ? 'Nominal melebihi sisa piutang '.rupiah($sale->receivable()).'.' : 'Nota ini tidak punya piutang.');
            }

            $payment = $sale->payments()->create(['wallet_id' => $walletId, 'paid_at' => Carbon::parse($date)->toDateString(), 'amount' => $amount, 'notes' => $notes]);
            app(WalletService::class)->sync($payment, $walletId, 'in', $amount, $date,
                'Penjualan '.$sale->invoice_number.($sale->customer_name ? ' ('.$sale->customer_name.')' : '').($notes ? ' · '.$notes : ''));
            $sale->refreshPaidAmount();

            return $payment;
        });
    }

    /** Uang dikembalikan ke pembeli (nota retur / lebih bayar): pembayaran minus, dompet berkurang. */
    public function refundSale(Sale $sale, float $amount, int $walletId, CarbonInterface|string $date, ?string $notes = null): SalePayment
    {
        return DB::transaction(function () use ($sale, $amount, $walletId, $date, $notes) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            $owed = -$sale->receivable();

            if ($amount <= 0 || $amount > $owed + 0.01) {
                throw new InvalidArgumentException($owed > 0
                    ? 'Uang yang perlu dikembalikan maksimal '.rupiah($owed).'.'
                    : 'Tidak ada uang yang perlu dikembalikan untuk nota ini.');
            }

            $payment = $sale->payments()->create(['wallet_id' => $walletId, 'paid_at' => Carbon::parse($date)->toDateString(), 'amount' => -$amount, 'notes' => $notes ?: 'Uang dikembalikan ke pembeli']);
            app(WalletService::class)->sync($payment, $walletId, 'out', $amount, $date,
                'Uang kembali '.$sale->invoice_number.($sale->customer_name ? ' ('.$sale->customer_name.')' : ''));
            $sale->refreshPaidAmount();

            return $payment;
        });
    }

    public function deleteSalePayment(SalePayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            app(WalletService::class)->forget($payment);
            $sale = $payment->sale;
            $payment->delete();
            $sale->refreshPaidAmount();
        });
    }

    public function deleteSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            app(WalletService::class)->forget($sale);
            foreach ($sale->payments()->get() as $payment) {
                app(WalletService::class)->forget($payment);
            }
            $sale->payments()->delete();
            $itemIds = StockMovement::where('source_type', $sale->getMorphClass())->where('source_id', $sale->id)->pluck('item_id');
            StockMovement::where('source_type', $sale->getMorphClass())->where('source_id', $sale->id)->delete();
            $sale->items()->delete();
            $sale->delete();
            Item::whereIn('id', $itemIds->unique())->get()->each->recalculateStock();
        });
    }

    /** Stok sistem disamakan dengan hasil hitung fisik; selisihnya jadi mutasi penyesuaian. */
    public function adjustItem(Item $item, float $actual, string $reason, CarbonInterface|string $date, ?string $notes = null, ?string $countedBy = null): ItemAdjustment
    {
        if ($actual < 0) {
            throw new InvalidArgumentException('Stok fisik tidak boleh minus.');
        }

        $date = Carbon::parse($date);

        return DB::transaction(function () use ($item, $actual, $reason, $date, $notes, $countedBy) {
            $item = Item::lockForUpdate()->findOrFail($item->id);
            $difference = $actual - (float) $item->stock;

            if (abs($difference) < 0.0001) {
                throw new InvalidArgumentException('Stok fisik sama dengan stok sistem ('.qty((float) $item->stock).'); tidak ada yang disesuaikan.');
            }

            $adjustment = ItemAdjustment::create([
                'adjustment_number' => DocumentNumber::next('item_adjustments', $date),
                'adjusted_at' => $date->toDateString(),
                'item_id' => $item->id,
                'system_qty' => (float) $item->stock,
                'actual_qty' => $actual,
                'difference' => $difference,
                'unit_cost' => (float) $item->cost_price,
                'reason' => $reason,
                'counted_by' => $countedBy,
                'notes' => $notes,
            ]);

            $this->moveItem($item, max($difference, 0), max(-$difference, 0), (float) $item->cost_price, 'adjustment', $adjustment, $date,
                $adjustment->adjustment_number.' · '.$adjustment->reasonLabel().($countedBy ? ' · oleh '.$countedBy : '').($notes ? ' · '.$notes : ''));

            return $adjustment;
        });
    }

    public function deleteItemAdjustment(ItemAdjustment $adjustment): void
    {
        DB::transaction(function () use ($adjustment) {
            $item = $adjustment->item;

            if ((float) $adjustment->difference > 0 && (float) $item->stock + 0.0001 < (float) $adjustment->difference) {
                throw new InvalidArgumentException('Stok '.$item->name.' tinggal '.qty((float) $item->stock).'; tambahan dari penyesuaian ini sudah terjual.');
            }

            StockMovement::where('source_type', $adjustment->getMorphClass())->where('source_id', $adjustment->id)->delete();
            $adjustment->delete();
            $item->recalculateStock();
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
