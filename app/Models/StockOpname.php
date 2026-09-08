<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Sesi perhitungan fisik gudang.
 */
class StockOpname extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'posted' => 'Dibukukan',
    ];

    protected $fillable = [
        'opname_number', 'opname_date', 'counted_by', 'status', 'posted_at', 'notes',
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
        return $this->hasMany(StockOpnameItem::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    /** Jumlah baris yang selisih antara sistem dan hitungan fisik. */
    public function discrepancyCount(): int
    {
        return $this->items()->where('difference', '!=', 0)->count();
    }
}
