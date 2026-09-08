<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id', 'item_id', 'system_qty', 'physical_qty', 'difference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:2',
            'physical_qty' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Selisih selalu diturunkan agar tidak bisa bertentangan dengan angkanya.
        static::saving(function (self $row) {
            $row->difference = (float) $row->physical_qty - (float) $row->system_qty;
        });
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
