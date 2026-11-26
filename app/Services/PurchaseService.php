<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseItem;
use App\Models\PipePiece;
use App\Models\PurchasePayment;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alur pembelian seperti di EMS: Pembelian (pesanan ke supplier) →
 * Penerimaan (barang datang, bisa bertahap, stok bertambah di sini) →
 * Pembayaran (dicicil dari dompet; sisanya hutang ke supplier).
 */
class PurchaseService
{
    public function __construct(private StockService $stock, private WalletService $wallets) {}

    /**
     * @param  array{purchased_at: string, supplier?: ?string, supplier_invoice?: ?string, buyer?: ?string, production_plan_id?: ?int, notes?: ?string, items: list<array{material_id: int, warehouse_id?: ?int, qty: float, bar_length_cm?: ?float, unit_price: float}>, paid_amount?: ?float, wallet_id?: ?int, receive_now?: bool}  $data
     */
    public function create(array $data): MaterialPurchase
    {
        return DB::transaction(function () use ($data) {
            $date = Carbon::parse($data['purchased_at']);
            $supplier = Supplier::resolve($data['supplier'] ?? null);
            $purchase = MaterialPurchase::create([
                'invoice_number' => DocumentNumber::next('material_purchases', $date),
                'purchased_at' => $date->toDateString(),
                'supplier_id' => $supplier?->id,
                'supplier' => $supplier?->name,
                'supplier_invoice' => $data['supplier_invoice'] ?? null,
                'buyer' => $data['buyer'] ?? null,
                'production_plan_id' => $data['production_plan_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $material = Material::with('warehouse')->findOrFail($row['material_id']);
                $warehouseId = (int) (($row['warehouse_id'] ?? null) ?: $material->warehouse_id);
                $this->assertWarehouse($material, $warehouseId);

                $qty = (float) $row['qty'];
                $price = (float) $row['unit_price'];
                $length = $material->isPipe() ? (float) (($row['bar_length_cm'] ?? null) ?: $material->bar_length_cm) : null;

                if ($material->isPipe() && $length <= 0) {
                    throw new RuntimeException("Panjang batang {$material->label()} belum diisi.");
                }

                $purchase->items()->create([
                    'material_id' => $material->id, 'warehouse_id' => $warehouseId, 'qty' => $qty, 'bar_length_cm' => $length,
                    'unit_price' => $price, 'subtotal' => $qty * $price,
                ]);

                $material->forceFill(['last_price' => $price])->save();
            }

            $purchase->refreshTotal();

            // Belanja di toko: barang langsung dibawa pulang, jadi sekalian diterima.
            if ($data['receive_now'] ?? true) {
                $this->receive($purchase, [
                    'received_at' => $date->toDateString(),
                    'received_by' => $data['buyer'] ?? null,
                    'items' => $purchase->items()->get()->map(fn (MaterialPurchaseItem $line) => [
                        'material_purchase_item_id' => $line->id, 'qty' => (float) $line->qty,
                    ])->all(),
                ]);
            }

            if ((float) ($data['paid_amount'] ?? 0) > 0) {
                $this->pay($purchase, (float) $data['paid_amount'], $data['wallet_id'] ?? null, $date->toDateString());
            }

            return $purchase->refresh();
        });
    }

    /**
     * Terima barang dari pembelian; baris dengan qty 0 dilewati.
     *
     * @param  array{received_at: string, received_by?: ?string, notes?: ?string, items: list<array{material_purchase_item_id: int, qty: float, warehouse_id?: ?int}>}  $data
     */
    public function receive(MaterialPurchase $purchase, array $data): PurchaseReceipt
    {
        if ($purchase->isService()) {
            throw new RuntimeException('Tagihan jasa vendor tidak punya barang untuk diterima.');
        }

        return DB::transaction(function () use ($purchase, $data) {
            $date = Carbon::parse($data['received_at']);
            $rows = collect($data['items'])->filter(fn (array $row) => (float) ($row['qty'] ?? 0) > 0);

            if ($rows->isEmpty()) {
                throw new RuntimeException('Isi jumlah barang yang diterima.');
            }

            $receipt = $purchase->receipts()->create([
                'receipt_number' => DocumentNumber::next('purchase_receipts', $date),
                'received_at' => $date->toDateString(),
                'received_by' => $data['received_by'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($rows as $row) {
                /** @var MaterialPurchaseItem $line */
                $line = $purchase->items()->with('material.warehouse')->lockForUpdate()->findOrFail($row['material_purchase_item_id']);
                $material = $line->material;
                $qty = (float) $row['qty'];

                if ($qty > $line->outstandingQty() + 0.0005) {
                    throw new RuntimeException("{$material->label()}: diterima ".qty($qty).', padahal sisa pesanan '.qty($line->outstandingQty()).' '.$material->purchaseUnit().'.');
                }

                if ($material->isPipe() && floor($qty) != $qty) {
                    throw new RuntimeException("{$material->label()}: pipa diterima per batang utuh.");
                }

                $warehouseId = (int) (($row['warehouse_id'] ?? null) ?: $line->warehouse_id ?: $material->warehouse_id);
                $this->assertWarehouse($material, $warehouseId);
                $warehouse = Warehouse::findOrFail($warehouseId);

                $receipt->items()->create(['material_purchase_item_id' => $line->id, 'warehouse_id' => $warehouse->id, 'qty' => $qty]);
                $note = $purchase->invoice_number.' · '.$receipt->receipt_number.($purchase->supplier ? ' · '.$purchase->supplier : '').($receipt->received_by ? ' · diterima '.$receipt->received_by : '');
                $price = (float) $line->unit_price;

                if ($material->isPipe()) {
                    $length = (float) $line->bar_length_cm;
                    $this->stock->receivePieces($material, $warehouse, (int) $qty, $length, $price / $length, 'pembelian', $receipt, $date, $note);
                } else {
                    $factor = $material->conversionFactor();
                    $this->stock->receiveUnits($material, $warehouse, $qty * $factor, $price / $factor, 'pembelian', $receipt, $date,
                        $note.($material->hasConversion() ? ' · '.qty($qty).' '.$material->unit : ''));
                }

                $line->forceFill(['received_qty' => (float) $line->received_qty + $qty])->save();
            }

            return $receipt;
        });
    }

    /** Hapus penerimaan: stoknya ditarik lagi, hanya bila barangnya belum terpakai. */
    public function deleteReceipt(PurchaseReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            $receipt->load('items.purchaseItem.material.stocks');
            $pieces = PipePiece::where('source_type', $receipt->getMorphClass())->where('source_id', $receipt->id)->get();

            if ($pieces->contains(fn (PipePiece $piece) => ! $piece->isUntouched())) {
                throw new RuntimeException("Sebagian pipa dari penerimaan {$receipt->receipt_number} sudah dipotong untuk produksi.");
            }

            $unitLines = $receipt->items->reject(fn ($line) => $line->purchaseItem->material->isPipe());

            foreach ($unitLines->groupBy(fn ($line) => $line->purchaseItem->material_id.'-'.$line->warehouse_id) as $lines) {
                $material = $lines->first()->purchaseItem->material;

                if ($material->stockIn($lines->first()->warehouse_id) + 0.0001 < $material->toStockQty((float) $lines->sum('qty'))) {
                    throw new RuntimeException("Stok {$material->label()} dari penerimaan {$receipt->receipt_number} sudah terpakai.");
                }
            }

            PipePiece::whereIn('id', $pieces->pluck('id'))->delete();
            $this->stock->forget($receipt);

            foreach ($receipt->items as $line) {
                $line->purchaseItem->forceFill(['received_qty' => max((float) $line->purchaseItem->received_qty - (float) $line->qty, 0)])->save();
            }

            $receipt->items()->delete();
            $receipt->delete();
        });
    }

    /** Sisa pesanan yang tidak jadi datang dibatalkan; tagihannya ikut turun. */
    public function cancelOutstanding(MaterialPurchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            foreach ($purchase->items()->get() as $line) {
                $line->forceFill(['cancelled_qty' => (float) $line->cancelled_qty + $line->outstandingQty()])->save();
            }

            $purchase->refreshTotal();

            if ((float) $purchase->paid_amount > (float) $purchase->total + 0.01) {
                throw new RuntimeException('Sudah dibayar '.rupiah($purchase->paid_amount).', lebih besar dari tagihan setelah dibatalkan ('.rupiah($purchase->total).'). Hapus atau kurangi pembayarannya dulu.');
            }
        });
    }

    public function pay(MaterialPurchase $purchase, float $amount, ?int $walletId, string $date, ?string $notes = null): PurchasePayment
    {
        if ($amount <= 0) {
            throw new RuntimeException('Nominal bayar harus lebih dari 0.');
        }

        if ($amount > $purchase->payable() + 0.01) {
            throw new RuntimeException('Nominal bayar melebihi sisa hutang '.rupiah($purchase->payable()).'.');
        }

        return DB::transaction(function () use ($purchase, $amount, $walletId, $date, $notes) {
            $payment = $purchase->payments()->create(['wallet_id' => $walletId, 'paid_at' => $date, 'amount' => $amount, 'notes' => $notes]);
            $this->wallets->sync($payment, $walletId, 'out', $amount, $date, 'Bayar pembelian '.$purchase->invoice_number.($purchase->supplier ? ' · '.$purchase->supplier : ''));
            $purchase->refreshPaidAmount();

            return $payment;
        });
    }

    public function deletePayment(PurchasePayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->wallets->forget($payment);
            $purchase = $payment->purchase;
            $payment->delete();
            $purchase->refreshPaidAmount();
        });
    }

    /** Hapus pembelian beserta penerimaannya — hanya bila barangnya belum terpakai. */
    public function delete(MaterialPurchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            if ($purchase->isService()) {
                throw new RuntimeException('Ini tagihan jasa vendor. Hapus lewat penerimaan di nota produksinya.');
            }

            foreach ($purchase->receipts()->get() as $receipt) {
                $this->deleteReceipt($receipt);
            }

            foreach ($purchase->payments()->get() as $payment) {
                $this->wallets->forget($payment);
            }

            $purchase->payments()->delete();
            $purchase->items()->delete();
            $purchase->delete();
        });
    }

    private function assertWarehouse(Material $material, int $warehouseId): void
    {
        if (! in_array($warehouseId, $material->allowedWarehouseIds(), true)) {
            throw new RuntimeException("{$material->label()} tidak bisa disimpan di gudang itu.");
        }
    }
}
