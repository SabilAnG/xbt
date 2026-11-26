<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penerimaan hasil proses vendor. unit_cost = bahan per unit + jasa vendor;
 * tagihan jasanya dicatat sebagai nota pembelian jenis "jasa".
 */
class ProductionReceipt extends Model
{
    protected $fillable = ['production_id', 'received_at', 'qty', 'service_cost_per_unit', 'unit_cost', 'material_purchase_id', 'notes'];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'qty' => 'decimal:3',
            'service_cost_per_unit' => 'decimal:2',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchase::class, 'material_purchase_id');
    }
}
