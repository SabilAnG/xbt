<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Satu baris kartu stok. Sumber kebenaran stok barang.
 */
class StockMovement extends Model
{
    public const TYPES = [
        'purchase' => 'Pembelian',
        'sale' => 'Penjualan',
        // Barang jadi yang masuk dari modul produksi.
        'production' => 'Hasil Produksi',
        'opname' => 'Stok Opname',
        'adjustment' => 'Penyesuaian',
    ];

    protected $fillable = [
        'item_id', 'type', 'qty_in', 'qty_out', 'balance_after',
        'unit_cost', 'source_type', 'source_id', 'moved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty_in' => 'decimal:2',
            'qty_out' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'moved_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
