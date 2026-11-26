<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Penerimaan barang dari satu pembelian. Stok bertambah di sini, bukan saat pesan. */
class PurchaseReceipt extends Model
{
    use RecordsActivity;

    public function activityLabel(): string
    {
        return 'Penerimaan barang '.$this->receipt_number;
    }

    protected $fillable = ['receipt_number', 'material_purchase_id', 'received_at', 'received_by', 'notes'];

    protected function casts(): array
    {
        return ['received_at' => 'date'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchase::class, 'material_purchase_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(MaterialMovement::class, 'source');
    }

    /** Nilai barang yang diterima (harga beli × jumlah). */
    public function value(): float
    {
        return (float) $this->items->sum(fn (PurchaseReceiptItem $line) => (float) $line->qty * (float) $line->purchaseItem->unit_price);
    }
}
