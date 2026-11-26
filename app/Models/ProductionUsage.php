<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bahan yang benar-benar terpakai satu produksi, dengan biaya beku. */
class ProductionUsage extends Model
{
    protected $fillable = ['production_id', 'material_id', 'part_name', 'component_name', 'qty', 'unit_cost', 'total_cost'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:2'];
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
