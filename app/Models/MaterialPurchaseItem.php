<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialPurchaseItem extends Model
{
    protected $fillable = [
        'material_purchase_id', 'material_id', 'component_id', 'rack_id', 'qty', 'unit_cost', 'subtotal',
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

    /** Komponen jadi yang dibeli (masuk gudang setengah jadi); kosong bila baris bahan baku. */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /** Nama barang di baris ini, bahan atau komponen. */
    public function goodsName(): ?string
    {
        return $this->material?->name ?? $this->component?->name;
    }

    public function goodsUnit(): ?string
    {
        return $this->material?->unit ?? $this->component?->unit;
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }
}
