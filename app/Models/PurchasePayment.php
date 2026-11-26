<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pembayaran nota pembelian bahan dari satu dompet. */
class PurchasePayment extends Model
{
    use RecordsActivity;

    public function activityLabel(): string
    {
        return 'Bayar pembelian '.($this->purchase?->invoice_number ?? '#'.$this->material_purchase_id).' · '.rupiah($this->amount);
    }

    protected $fillable = ['material_purchase_id', 'wallet_id', 'supplier_deposit_id', 'paid_at', 'amount', 'notes'];

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchase::class, 'material_purchase_id');
    }

    /** Diisi bila dibayar dari uang muka (bukan dari dompet). */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(SupplierDeposit::class, 'supplier_deposit_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
