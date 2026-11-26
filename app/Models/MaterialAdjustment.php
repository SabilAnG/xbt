<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Stok awal, koreksi hitung fisik, atau barang rusak.
 *
 * Untuk pipa masuk: qty = jumlah potongan, length_cm = panjang tiap potongan.
 * Untuk pipa keluar: pipe_piece_id = potongan yang diafkir.
 */
class MaterialAdjustment extends Model
{
    public const REASONS = [
        'stok_awal' => 'Stok awal',
        'koreksi' => 'Koreksi hitung fisik',
        'rusak' => 'Rusak / afkir',
    ];

    protected $fillable = [
        'adjustment_number', 'adjusted_at', 'material_id', 'warehouse_id', 'pipe_piece_id',
        'direction', 'qty', 'length_cm', 'unit_cost', 'reason', 'counted_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'adjusted_at' => 'date',
            'qty' => 'decimal:3',
            'length_cm' => 'decimal:2',
            'unit_cost' => 'decimal:4',
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

    public function movements(): MorphMany
    {
        return $this->morphMany(MaterialMovement::class, 'source');
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
