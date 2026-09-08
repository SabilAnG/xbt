<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Kartu stok bahan baku — sumber kebenaran stok produksi.
 */
class MaterialMovement extends Model
{
    public const TYPES = [
        'purchase' => 'Pembelian Bahan',
        'production' => 'Dipakai Produksi',
        'adjustment' => 'Penyesuaian',
        'opname' => 'Stok Opname',
    ];

    protected $fillable = [
        'material_id', 'rack_id', 'type', 'qty_in', 'qty_out',
        'balance_after', 'unit_cost', 'source_type', 'source_id',
        'moved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty_in' => 'decimal:3',
            'qty_out' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'moved_at' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
