<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionMaterial extends Model
{
    protected $fillable = [
        'production_id', 'material_id', 'rack_id', 'qty', 'unit_cost', 'subtotal',
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

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
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
