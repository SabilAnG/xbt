<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialPurchaseItem extends Model
{
    protected $fillable = [
        'material_purchase_id', 'material_id', 'rack_id', 'qty', 'unit_cost', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            $row->subtotal = (float) $row->qty * (float) $row->unit_cost;
        });
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchase::class, 'material_purchase_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }
}
