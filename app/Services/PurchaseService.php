<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseItem;
use App\Models\PipePiece;
use App\Models\PurchasePayment;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierDeposit;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
     * @param  array{purchased_at: string, supplier?: ?string, supplier_location?: ?string, supplier_invoice?: ?string, buyer?: ?string, production_plan_id?: ?int, notes?: ?string, items: list<array{material_id: int, warehouse_id?: ?int, qty: float, bar_length_cm?: ?float, unit_price: float}>, paid_amount?: ?float, wallet_id?: ?int, receive_now?: bool}  $data
     */
    public function create(array $data): MaterialPurchase
    {
        return DB::transaction(function () use ($data) {
            $date = Carbon::parse($data['purchased_at']);
            $supplier = Supplier::resolve($data['supplier'] ?? null);
            $location = trim((string) ($data['supplier_location'] ?? '')) ?: null;

            // Lokasi pertama yang diisi jadi alamat di Master Supplier.
            if ($supplier && $location && blank($supplier->address)) {
                $supplier->update(['address' => $location]);
            }

            $purchase = MaterialPurchase::create([
                'invoice_number' => DocumentNumber::next('material_purchases', $date),
                'purchased_at' => $date->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'supplier_id' => $supplier?->id,
                'supplier' => $supplier?->name,
                'supplier_location' => $location ?? $supplier?->address,
                'supplier_invoice' => $data['supplier_invoice'] ?? null,
                'buyer' => $data['buyer'] ?? null,
                'production_plan_id' => $data['production_plan_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $material = Material::with('warehouse')->findOrFail($row['material_id']);

                if (! $material->is_purchasable) {
                    throw new RuntimeException("{$material->label()} ditandai hanya diproduksi sendiri, tidak bisa dibeli.");
                }

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

    /**
     * Bayar beberapa tagihan satu supplier sekaligus. Saldo uang muka dipakai
     * dulu (bila dipilih), lalu uang dari dompet; tagihan terlama dilunasi dulu.
     *
     * @param  array<int, int>  $purchaseIds
     * @return Collection<int, PurchasePayment>
     */
    public function payMany(Supplier $supplier, array $purchaseIds, float $cash, ?int $walletId, bool $useDeposit, string $date, ?string $notes = null): Collection
    {
        return DB::transaction(function () use ($supplier, $purchaseIds, $cash, $walletId, $useDeposit, $date, $notes) {
            $bills = $supplier->purchases()->whereIn('id', $purchaseIds)->unpaid()->lockForUpdate()
                ->orderBy('purchased_at')->orderBy('id')->get();
            $owed = (float) $bills->sum(fn (MaterialPurchase $bill) => $bill->payable());

            if ($bills->isEmpty()) {
                throw new RuntimeException('Pilih minimal satu tagihan yang belum lunas.');
            }

            $deposits = $useDeposit
                ? $supplier->deposits()->get()->map(fn (SupplierDeposit $deposit) => ['deposit' => $deposit, 'left' => $deposit->remaining()])->filter(fn (array $row) => $row['left'] > 0.004)->values()
                : collect();
            $fromDeposit = min((float) $deposits->sum('left'), $owed);

            if ($cash < 0 || $cash > $owed - $fromDeposit + 0.01) {
                throw new RuntimeException('Nominal dari dompet maksimal '.rupiah(max($owed - $fromDeposit, 0)).' (sisa tagihan yang dipilih'.($fromDeposit > 0 ? ' setelah dipotong uang muka' : '').').');
            }

            if ($cash > 0 && ! $walletId) {
                throw new RuntimeException('Pilih dompet sumber uangnya.');
            }

            if ($cash + $fromDeposit <= 0) {
                throw new RuntimeException('Isi nominal yang dibayar atau pakai saldo uang muka.');
            }

            $payments = collect();
            $cashLeft = $cash;

            foreach ($bills as $bill) {
                $due = $bill->payable();

                // 1. Dari uang muka (bisa beberapa DP).
                foreach ($deposits as $i => $row) {
                    if ($due <= 0.004 || $row['left'] <= 0.004) {
                        continue;
                    }
                    $amount = round(min($due, $row['left']), 2);
                    $payments->push($bill->payments()->create([
                        'supplier_deposit_id' => $row['deposit']->id, 'paid_at' => $date, 'amount' => $amount,
                        'notes' => trim('Dari uang muka '.$row['deposit']->paid_at->format('d/m/Y').' '.($notes ?? '')),
                    ]));
                    $deposits[$i] = ['deposit' => $row['deposit'], 'left' => $row['left'] - $amount];
                    $due -= $amount;
                }

                // 2. Dari dompet.
                if ($due > 0.004 && $cashLeft > 0.004) {
                    $amount = round(min($due, $cashLeft), 2);
                    $payment = $bill->payments()->create(['wallet_id' => $walletId, 'paid_at' => $date, 'amount' => $amount, 'notes' => $notes]);
                    $this->wallets->sync($payment, $walletId, 'out', $amount, $date, 'Bayar '.$bill->invoice_number.' · '.$supplier->name);
                    $payments->push($payment);
                    $cashLeft -= $amount;
                }

                $bill->refreshPaidAmount();
            }

            return $payments;
        });
    }

    /** Uang muka ke supplier: keluar dari dompet sekarang, dipakai membayar tagihan nanti. */
    public function giveDeposit(Supplier $supplier, float $amount, int $walletId, string $date, ?string $notes = null): SupplierDeposit
    {
        if ($amount <= 0) {
            throw new RuntimeException('Nominal uang muka harus lebih dari 0.');
        }

        return DB::transaction(function () use ($supplier, $amount, $walletId, $date, $notes) {
            $deposit = $supplier->deposits()->create(['wallet_id' => $walletId, 'paid_at' => $date, 'amount' => $amount, 'notes' => $notes]);
            $this->wallets->sync($deposit, $walletId, 'out', $amount, $date, 'Uang muka ke '.$supplier->name.($notes ? ' · '.$notes : ''));

            return $deposit;
        });
    }

    public function deleteDeposit(SupplierDeposit $deposit): void
    {
        if ($deposit->payments()->exists()) {
            throw new RuntimeException('Uang muka ini sudah dipakai membayar tagihan. Hapus pembayaran tagihannya dulu.');
        }

        DB::transaction(function () use ($deposit) {
            $this->wallets->forget($deposit);
            $deposit->delete();
        });
    }

    /**
     * Retur ke supplier: barang keluar dari gudang tempat diterima, tagihan
     * turun senilai harga beli. Uang yang sudah terlanjur dibayar bisa
     * dikembalikan supplier ke dompet.
     *
     * @param  array{returned_at: string, notes?: ?string, refund_amount?: float|int|string|null, wallet_id?: ?int, items: array<int, array{material_purchase_item_id: int, qty: float|int|string}>}  $data
     */
    public function returnGoods(MaterialPurchase $purchase, array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($purchase, $data) {
            $purchase = MaterialPurchase::lockForUpdate()->findOrFail($purchase->id);
            $lines = $purchase->items()->with('material.stocks', 'material.warehouse')->get()->keyBy('id');
            $rows = collect($data['items'])->filter(fn (array $row) => (float) $row['qty'] > 0);

            if ($rows->isEmpty()) {
                throw new RuntimeException('Isi qty barang yang diretur.');
            }

            $return = $purchase->returns()->create([
                'return_number' => DocumentNumber::next('purchase_returns', Carbon::parse($data['returned_at'])),
                'returned_at' => $data['returned_at'],
                'notes' => $data['notes'] ?? null,
            ]);
            $note = $return->return_number.' · retur ke '.($purchase->supplier ?? 'supplier');

            foreach ($rows as $row) {
                $line = $lines->get($row['material_purchase_item_id']) ?? throw new RuntimeException('Barang tidak ada di pembelian ini.');
                $qty = (float) $row['qty'];

                if ($qty > $line->returnableQty() + 0.0001) {
                    throw new RuntimeException("{$line->material->name}: yang bisa diretur maksimal ".qty($line->returnableQty()).' (sudah diterima, belum diretur).');
                }

                // Pipa: batang utuh dari penerimaan pembelian ini yang belum dipotong.
                if ($line->material->isPipe()) {
                    if (floor($qty) != $qty) {
                        throw new RuntimeException("{$line->material->name}: pipa diretur per batang utuh.");
                    }

                    $pieces = PipePiece::query()->where('material_id', $line->material_id)->where('status', PipePiece::AVAILABLE)
                        ->where('source_type', (new PurchaseReceipt)->getMorphClass())->whereIn('source_id', $purchase->receipts()->pluck('id'))
                        ->orderByDesc('id')->get()->filter(fn (PipePiece $piece) => $piece->isUntouched())->take((int) $qty);

                    if ($pieces->count() < $qty) {
                        throw new RuntimeException("{$line->material->name}: hanya {$pieces->count()} batang utuh (belum dipotong) yang masih ada di gudang.");
                    }

                    foreach ($pieces as $piece) {
                        $this->stock->recordPiece($piece, $piece->warehouse, 'retur', 0, (float) $piece->length_cm, $return, $data['returned_at'], $note.' · '.$piece->code);
                        $piece->forceFill(['status' => PipePiece::RETURNED])->save();
                        $return->items()->create([
                            'material_purchase_item_id' => $line->id, 'warehouse_id' => $piece->warehouse_id, 'pipe_piece_id' => $piece->id,
                            'qty' => 1, 'unit_price' => (float) $line->unit_price, 'subtotal' => (float) $line->unit_price,
                        ]);
                    }

                    $this->stock->recalculate($line->material);
                    $line->forceFill(['returned_qty' => (float) $line->returned_qty + $qty])->save();

                    continue;
                }

                $warehouseId = PurchaseReceiptItem::query()->where('material_purchase_item_id', $line->id)->latest('id')->value('warehouse_id')
                    ?? $line->warehouse_id ?? $line->material->warehouse_id;
                $this->stock->issueUnits($line->material, Warehouse::findOrFail($warehouseId), $line->material->toStockQty($qty), 'retur', $return, $data['returned_at'], $note);

                $return->items()->create([
                    'material_purchase_item_id' => $line->id, 'warehouse_id' => $warehouseId,
                    'qty' => $qty, 'unit_price' => (float) $line->unit_price, 'subtotal' => $qty * (float) $line->unit_price,
                ]);
                $line->forceFill(['returned_qty' => (float) $line->returned_qty + $qty])->save();
            }

            $purchase->refreshTotal();
            $total = (float) $return->items()->sum('subtotal');
            $refund = round((float) ($data['refund_amount'] ?? 0), 2);
            $overpaid = (float) $purchase->paid_amount - (float) $purchase->total;

            if ($refund > $total + 0.01 || $refund > (float) $purchase->paid_amount + 0.01) {
                throw new RuntimeException('Uang kembali maksimal '.rupiah(min($total, (float) $purchase->paid_amount)).'.');
            }

            if ($overpaid - $refund > 0.01) {
                throw new RuntimeException('Pembelian ini sudah dibayar '.rupiah($purchase->paid_amount).', lebih dari tagihan setelah retur ('.rupiah($purchase->total).'). Isi uang kembali dari supplier minimal '.rupiah($overpaid).'.');
            }

            $payment = null;
            if ($refund > 0) {
                if (empty($data['wallet_id'])) {
                    throw new RuntimeException('Pilih dompet tempat uang kembali dari supplier masuk.');
                }
                $payment = $purchase->payments()->create(['wallet_id' => $data['wallet_id'], 'paid_at' => $data['returned_at'], 'amount' => -$refund, 'notes' => 'Uang kembali '.$return->return_number]);
                $this->wallets->sync($payment, (int) $data['wallet_id'], 'in', $refund, $data['returned_at'], 'Uang kembali retur '.$return->return_number.' · '.($purchase->supplier ?? ''));
                $purchase->refreshPaidAmount();
            }

            $return->forceFill(['total' => $total, 'refund_amount' => $refund, 'wallet_id' => $payment ? $data['wallet_id'] : null, 'purchase_payment_id' => $payment?->id])->save();

            return $return;
        });
    }

    /** Batalkan retur: barang masuk gudang lagi, tagihan & uang kembali dipulihkan. */
    public function deleteReturn(PurchaseReturn $return): void
    {
        DB::transaction(function () use ($return) {
            $purchase = $return->purchase;

            foreach ($return->items()->with('purchaseItem')->get() as $row) {
                $row->purchaseItem->forceFill(['returned_qty' => max((float) $row->purchaseItem->returned_qty - (float) $row->qty, 0)])->save();
            }

            if ($return->purchase_payment_id && ($payment = PurchasePayment::find($return->purchase_payment_id))) {
                $this->wallets->forget($payment);
                $payment->delete();
            }

            PipePiece::whereIn('id', $return->items()->whereNotNull('pipe_piece_id')->pluck('pipe_piece_id'))->update(['status' => PipePiece::AVAILABLE]);
            $this->stock->forget($return);
            $return->items()->delete();
            $return->delete();
            $purchase->refreshTotal();
            $purchase->refreshPaidAmount();
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

            foreach ($purchase->returns()->get() as $return) {
                $this->deleteReturn($return);
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
