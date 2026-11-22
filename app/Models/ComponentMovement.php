<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Kartu stok gudang setengah jadi. */
class ComponentMovement extends Model
{
    /** Jenis yang bisa dicatat manual di gudang setengah jadi. */
    public const TYPES = [
        'masuk' => 'Hasil dibuat (masuk)',
        'keluar' => 'Dipakai rakit (keluar)',
        'penyesuaian' => 'Penyesuaian / koreksi',
    ];

    /** Semua label, termasuk mutasi otomatis dari dokumen lain. */
    public const LABELS = self::TYPES + [
        'pembelian' => 'Dibeli jadi',
    ];

    protected $fillable = ['component_id', 'source_type', 'source_id', 'type', 'qty_in', 'qty_out', 'balance_after', 'consumes_materials', 'moved_at', 'notes'];

    protected function casts(): array
    {
        return [
            'qty_in' => 'decimal:3',
            'qty_out' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'consumes_materials' => 'boolean',
            'moved_at' => 'date',
        ];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** Mutasi otomatis (mis. dari pembelian) dihapus lewat dokumen asalnya. */
    public function isManual(): bool
    {
        return $this->source_type === null;
    }

    /** Bahan baku yang terpotong saat komponen ini dibuat. */
    public function materialMovements(): MorphMany
    {
        return $this->morphMany(MaterialMovement::class, 'source');
    }
}
