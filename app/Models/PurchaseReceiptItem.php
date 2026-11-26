<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris penerimaan; qty dalam satuan beli (batang untuk pipa). */
class PurchaseReceiptItem extends Model
{
    protected $fillable = ['purchase_receipt_id', 'material_purchase_item_id', 'warehouse_id', 'qty'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchaseItem::class, 'material_purchase_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
