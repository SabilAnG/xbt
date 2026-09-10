<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Satu baris kartu stok barang produksi.
 *
 * Inilah kebenaran stok. `production_items.stock` cuma cache yang diturunkan
 * dari sini, supaya setiap perubahan punya baris yang menjelaskan asalnya —
 * kapan, karena dokumen apa, dan berapa saldonya sesudah itu.
 */
class ProductionItemMovement extends Model
{
    public const TYPES = [
        'opname' => 'Stok Opname',
        'purchase' => 'Pembelian',
        'production' => 'Produksi',
        'adjustment' => 'Penyesuaian',
    ];

    protected $fillable = [
        'production_item_id', 'warehouse_id', 'type',
        'qty_in', 'qty_out', 'balance_after', 'unit_cost',
        'source_type', 'source_id', 'moved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty_in' => 'decimal:3',
            'qty_out' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'moved_at' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductionItem::class, 'production_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function displayType(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Perubahan bersih baris ini, bertanda: masuk positif, keluar negatif. */
    public function delta(): float
    {
        return (float) $this->qty_in - (float) $this->qty_out;
    }
}
