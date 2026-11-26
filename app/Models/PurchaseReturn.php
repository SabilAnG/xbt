<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Barang pembelian yang dikembalikan ke supplier: stok keluar, tagihan turun. */
class PurchaseReturn extends Model
{
    use RecordsActivity;

    public function activityLabel(): string
    {
        return 'Retur pembelian '.$this->return_number;
    }

    protected $fillable = ['return_number', 'material_purchase_id', 'returned_at', 'total', 'refund_amount', 'wallet_id', 'purchase_payment_id', 'notes'];

    protected function casts(): array
    {
        return ['returned_at' => 'date', 'total' => 'decimal:2', 'refund_amount' => 'decimal:2'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchase::class, 'material_purchase_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
