<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Membukukan dan membatalkan nota.
 *
 * Semua pergerakan stok dan kas melewati kelas ini, supaya cuma ada satu tempat
 * yang boleh menulis stock_movements / wallet_transactions. Nota berstatus
 * `draft` sengaja tidak menyentuh apa pun — angka baru nyata setelah dibukukan.
 *
 * Harga pokok memakai biaya pembelian terakhir (last cost): saat pembelian
 * dibukukan, items.cost_price ikut diperbarui; saat penjualan dibukukan, nilai
 * itu dibekukan ke sale_items.unit_cost agar laba historis tidak ikut berubah.
 */
class PostingService
{
    // ---------------------------------------------------------------- purchase

    public function postPurchase(Purchase $purchase): void
    {
        if ($purchase->isPosted()) {
            throw new RuntimeException('Nota pembelian ini sudah dibukukan.');
        }

        $purchase->loadMissing('items.item');

        if ($purchase->items->isEmpty()) {
            throw new RuntimeException('Tidak bisa membukukan pembelian tanpa baris barang.');
        }

        DB::transaction(function () use ($purchase) {
            $purchase->recalculateTotals();

            foreach ($purchase->items as $line) {
                $this->addStock(
                    item: $line->item,
                    qtyIn: (float) $line->qty,
                    unitCost: (float) $line->unit_cost,
                    type: 'purchase',
                    source: $purchase,
                    movedAt: $purchase->purchased_at,
                    notes: 'Pembelian '.$purchase->invoice_number,
                );

                // Biaya pembelian terakhir menjadi harga pokok berjalan.
                $line->item->forceFill(['cost_price' => $line->unit_cost])->save();
            }

            $this->moveWallet(
                wallet: $purchase->wallet,
                direction: 'out',
                amount: (float) $purchase->total,
                source: $purchase,
                occurredAt: $purchase->purchased_at,
                description: 'Pembelian '.$purchase->invoice_number,
            );

            $purchase->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
        });
    }

    public function unpostPurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $this->reverse($purchase);
            $purchase->forceFill(['status' => 'draft', 'posted_at' => null])->save();
        });
    }

    // -------------------------------------------------------------------- sale

    public function postSale(Sale $sale): void
    {
        if ($sale->isPosted()) {
            throw new RuntimeException('Nota penjualan ini sudah dibukukan.');
        }

        $sale->loadMissing('items.item');

        if ($sale->items->isEmpty()) {
            throw new RuntimeException('Tidak bisa membukukan penjualan tanpa baris barang.');
        }

        // Stok dicek lebih dulu supaya nota tidak setengah terbukukan.
        foreach ($sale->items as $line) {
            if ((float) $line->item->stock < (float) $line->qty) {
                throw new RuntimeException(sprintf(
                    'Stok %s tidak cukup: tersedia %s, diminta %s.',
                    $line->item->name,
                    rtrim(rtrim((string) $line->item->stock, '0'), '.'),
                    rtrim(rtrim((string) $line->qty, '0'), '.'),
                ));
            }
        }

        DB::transaction(function () use ($sale) {
            $sale->recalculateTotals();
            $totalCost = 0.0;

            foreach ($sale->items as $line) {
                $unitCost = (float) $line->item->cost_price;
                $line->forceFill(['unit_cost' => $unitCost])->save();
                $totalCost += $unitCost * (float) $line->qty;

                $this->removeStock(
                    item: $line->item,
                    qtyOut: (float) $line->qty,
                    unitCost: $unitCost,
                    type: 'sale',
                    source: $sale,
                    movedAt: $sale->sold_at,
                    notes: 'Penjualan '.$sale->invoice_number,
                );
            }

            $this->moveWallet(
                wallet: $sale->wallet,
                direction: 'in',
                amount: (float) $sale->total,
                source: $sale,
                occurredAt: $sale->sold_at,
                description: 'Penjualan '.$sale->invoice_number,
            );

            $sale->forceFill([
                'total_cost' => $totalCost,
                'status' => 'posted',
                'posted_at' => now(),
            ])->save();
        });
    }

    public function unpostSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $this->reverse($sale);
            $sale->forceFill(['status' => 'draft', 'posted_at' => null, 'total_cost' => 0])->save();
        });
    }

    // ----------------------------------------------------------------- expense

    public function postExpense(Expense $expense): void
    {
        if ($expense->isPosted()) {
            throw new RuntimeException('Pengeluaran ini sudah dibukukan.');
        }

        DB::transaction(function () use ($expense) {
            $this->moveWallet(
                wallet: $expense->wallet,
                direction: 'out',
                amount: (float) $expense->amount,
                source: $expense,
                occurredAt: $expense->spent_at,
                description: $expense->description ?: ('Pengeluaran '.$expense->reference_number),
            );

            $expense->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
        });
    }

    public function unpostExpense(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $this->reverse($expense);
            $expense->forceFill(['status' => 'draft', 'posted_at' => null])->save();
        });
    }

    // ------------------------------------------------------------------ opname

    public function postOpname(StockOpname $opname): void
    {
        if ($opname->isPosted()) {
            throw new RuntimeException('Stok opname ini sudah dibukukan.');
        }

        $opname->loadMissing('items.item');

        DB::transaction(function () use ($opname) {
            foreach ($opname->items as $line) {
                $diff = (float) $line->difference;

                if (abs($diff) < 0.0001) {
                    continue; // cocok, tidak perlu koreksi
                }

                $movedAt = $opname->opname_date;
                $note = 'Stok opname '.$opname->opname_number;

                $diff > 0
                    ? $this->addStock($line->item, $diff, (float) $line->item->cost_price, 'opname', $opname, $movedAt, $note)
                    : $this->removeStock($line->item, abs($diff), (float) $line->item->cost_price, 'opname', $opname, $movedAt, $note);
            }

            $opname->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
        });
    }

    // ----------------------------------------------------------------- helpers

    private function addStock(Item $item, float $qtyIn, float $unitCost, string $type, $source, $movedAt, ?string $notes = null): void
    {
        $balance = (float) $item->stock + $qtyIn;

        StockMovement::create([
            'item_id' => $item->id,
            'type' => $type,
            'qty_in' => $qtyIn,
            'qty_out' => 0,
            'balance_after' => $balance,
            'unit_cost' => $unitCost,
            'source_type' => $source::class,
            'source_id' => $source->id,
            'moved_at' => $movedAt,
            'notes' => $notes,
        ]);

        $item->forceFill(['stock' => $balance])->save();
    }

    private function removeStock(Item $item, float $qtyOut, float $unitCost, string $type, $source, $movedAt, ?string $notes = null): void
    {
        $balance = (float) $item->stock - $qtyOut;

        StockMovement::create([
            'item_id' => $item->id,
            'type' => $type,
            'qty_in' => 0,
            'qty_out' => $qtyOut,
            'balance_after' => $balance,
            'unit_cost' => $unitCost,
            'source_type' => $source::class,
            'source_id' => $source->id,
            'moved_at' => $movedAt,
            'notes' => $notes,
        ]);

        $item->forceFill(['stock' => $balance])->save();
    }

    private function moveWallet(?Wallet $wallet, string $direction, float $amount, $source, $occurredAt, ?string $description = null): void
    {
        // Dompet opsional: nota bisa dicatat tanpa memilih sumber dana.
        if (! $wallet || abs($amount) < 0.0001) {
            return;
        }

        $balance = $direction === 'in'
            ? (float) $wallet->current_balance + $amount
            : (float) $wallet->current_balance - $amount;

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balance,
            'source_type' => $source::class,
            'source_id' => $source->id,
            'occurred_at' => $occurredAt,
            'description' => $description,
        ]);

        $wallet->forceFill(['current_balance' => $balance])->save();
    }

    /**
     * Hapus jejak sebuah nota lalu hitung ulang saldo/stok yang terdampak.
     * Menghitung ulang lebih aman daripada menerapkan mutasi kebalikan, karena
     * hasilnya tetap benar walau ada nota lain yang dibukukan sesudahnya.
     */
    private function reverse($document): void
    {
        $itemIds = StockMovement::query()
            ->where('source_type', $document::class)
            ->where('source_id', $document->id)
            ->pluck('item_id')
            ->unique();

        $walletIds = WalletTransaction::query()
            ->where('source_type', $document::class)
            ->where('source_id', $document->id)
            ->pluck('wallet_id')
            ->unique();

        StockMovement::where('source_type', $document::class)->where('source_id', $document->id)->delete();
        WalletTransaction::where('source_type', $document::class)->where('source_id', $document->id)->delete();

        Item::whereIn('id', $itemIds)->get()->each->recalculateStock();
        Wallet::whereIn('id', $walletIds)->get()->each->recalculateBalance();
    }
}
