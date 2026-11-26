<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    protected $fillable = ['purchase_return_id', 'material_purchase_item_id', 'warehouse_id', 'qty', 'unit_price', 'subtotal'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'unit_price' => 'decimal:2', 'subtotal' => 'decimal:2'];
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
