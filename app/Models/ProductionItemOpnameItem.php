<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionItemOpnameItem extends Model
{
    protected $fillable = [
        'production_item_opname_id', 'production_item_id',
        'system_qty', 'physical_qty', 'difference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:3',
            'physical_qty' => 'decimal:3',
            'difference' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        // Selisih selalu diturunkan, tidak pernah diketik, supaya tidak bisa
        // bertentangan dengan kedua angkanya.
        static::saving(function (self $row) {
            $row->difference = (float) $row->physical_qty - (float) $row->system_qty;
        });
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(ProductionItemOpname::class, 'production_item_opname_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductionItem::class, 'production_item_id');
    }
}
