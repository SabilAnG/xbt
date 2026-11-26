<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Baris pembelian. Untuk pipa: qty = jumlah batang, unit_price = harga per batang. */
class MaterialPurchaseItem extends Model
{
    protected $fillable = ['material_purchase_id', 'material_id', 'warehouse_id', 'qty', 'received_qty', 'cancelled_qty', 'returned_qty', 'bar_length_cm', 'unit_price', 'subtotal'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'received_qty' => 'decimal:3',
            'cancelled_qty' => 'decimal:3',
            'bar_length_cm' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchase::class, 'material_purchase_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    /** Yang masih ditunggu datang (satuan beli). */
    /** Yang sudah diterima dan belum diretur (boleh dikembalikan ke supplier). */
    public function returnableQty(): float
    {
        return max(round((float) $this->received_qty - (float) $this->returned_qty, 4), 0);
    }

    public function outstandingQty(): float
    {
        return max(round((float) $this->qty - (float) $this->received_qty - (float) $this->cancelled_qty, 3), 0);
    }

    /** Gudang tujuan barang ini (default: gudang asal bahan). */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
