<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Kartu stok gudang setengah jadi. */
class ComponentMovement extends Model
{
    public const TYPES = [
        'masuk' => 'Hasil dibuat (masuk)',
        'keluar' => 'Dipakai rakit (keluar)',
        'penyesuaian' => 'Penyesuaian / koreksi',
    ];

    protected $fillable = ['component_id', 'type', 'qty_in', 'qty_out', 'balance_after', 'consumes_materials', 'moved_at', 'notes'];

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

    /** Bahan baku yang terpotong saat komponen ini dibuat. */
    public function materialMovements(): MorphMany
    {
        return $this->morphMany(MaterialMovement::class, 'source');
    }
}
