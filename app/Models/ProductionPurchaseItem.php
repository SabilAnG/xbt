<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris nota pembelian bahan.
 *
 * `qty` dan `unit_cost` memakai satuan BELI — tujuh batang seharga Rp90.000
 * per batang — persis seperti tertulis di nota tokonya. Konversi ke satuan
 * pakai baru terjadi saat dibukukan.
 */
class ProductionPurchaseItem extends Model
{
    protected $fillable = [
        'production_purchase_id', 'production_item_id', 'warehouse_id',
        'qty', 'unit_cost', 'subtotal', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Subtotal selalu diturunkan; dua angka yang bisa berselisih adalah
        // dua angka yang tidak bisa dipercaya.
        static::saving(function (self $row) {
            $row->subtotal = (float) $row->qty * (float) $row->unit_cost;
        });
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ProductionPurchase::class, 'production_purchase_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductionItem::class, 'production_item_id');
    }

    /** Gudang tujuan baris ini — tiap bahan boleh mendarat di tempat berbeda. */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** Kebutuhan baris ini dalam satuan pakai: 7 batang -> 42.000 mm. */
    public function baseQty(): float
    {
        return $this->item?->toBase((float) $this->qty) ?? 0.0;
    }
}
