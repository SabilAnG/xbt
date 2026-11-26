<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pembayaran nota pembelian bahan dari satu dompet. */
class PurchasePayment extends Model
{
    protected $fillable = ['material_purchase_id', 'wallet_id', 'paid_at', 'amount', 'notes'];

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchase::class, 'material_purchase_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
