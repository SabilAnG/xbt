<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Kartu stok bahan per gudang. Untuk pipa, qty dalam cm. */
class MaterialMovement extends Model
{
    public const TYPES = [
        'pembelian' => 'Pembelian',
        'pemakaian' => 'Pemakaian produksi',
        'sisa' => 'Sisa potong',
        'afkir' => 'Afkir',
        'penyesuaian' => 'Penyesuaian',
        'hasil' => 'Hasil produksi',
        'terima_vendor' => 'Terima dari vendor',
        'pindah' => 'Pindah gudang',
    ];

    protected $fillable = [
        'material_id', 'warehouse_id', 'pipe_piece_id', 'type', 'qty_in', 'qty_out', 'unit_cost',
        'balance_after', 'source_type', 'source_id', 'moved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty_in' => 'decimal:3',
            'qty_out' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'balance_after' => 'decimal:3',
            'moved_at' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(PipePiece::class, 'pipe_piece_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
