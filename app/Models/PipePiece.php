<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Satu batang atau potongan pipa. Batang utuh ada di Gudang Bahan Baku; begitu
 * dipotong, sisanya pindah ke Gudang Stok Sisa Produksi. Sisa yang lebih
 * pendek dari batas pakai bahan menjadi afkir.
 */
class PipePiece extends Model
{
    public const AVAILABLE = 'tersedia';

    public const USED_UP = 'habis';

    public const SCRAP = 'afkir';

    /** Batang utuh yang dikembalikan ke supplier. */
    public const RETURNED = 'retur';

    protected $fillable = [
        'code', 'material_id', 'warehouse_id', 'initial_length_cm', 'length_cm', 'cost_per_cm', 'status', 'source_type', 'source_id',
    ];

    protected function casts(): array
    {
        return [
            'initial_length_cm' => 'decimal:2',
            'length_cm' => 'decimal:2',
            'cost_per_cm' => 'decimal:4',
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

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function cuts(): HasMany
    {
        return $this->hasMany(ProductionCut::class);
    }

    /** Belum pernah dipotong atau diafkir, jadi pembeliannya masih boleh dihapus. */
    public function isUntouched(): bool
    {
        return $this->status === self::AVAILABLE
            && round((float) $this->length_cm, 2) === round((float) $this->initial_length_cm, 2)
            && ! $this->cuts()->exists();
    }

    public function value(): float
    {
        return (float) $this->length_cm * (float) $this->cost_per_cm;
    }

    /** Foto stok dari stok opname, terbaru dulu. */
    public function stockPhotos(): MorphMany
    {
        return $this->morphMany(StockPhoto::class, 'subject')->latest('taken_at')->latest('id');
    }
}
