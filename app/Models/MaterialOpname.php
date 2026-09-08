<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Sesi hitung fisik bahan baku di gudang produksi.
 */
class MaterialOpname extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'posted' => 'Dibukukan',
    ];

    protected $fillable = [
        'opname_number', 'opname_date', 'warehouse_id', 'counted_by',
        'status', 'posted_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opname_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialOpnameItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function materialMovements(): MorphMany
    {
        return $this->morphMany(MaterialMovement::class, 'source');
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    /** Berapa baris yang selisih antara sistem dan hitungan fisik. */
    public function discrepancyCount(): int
    {
        return $this->items()->where('difference', '!=', 0)->count();
    }
}
