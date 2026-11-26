<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bahan satu komponen. qty dalam satuan bahan, atau cm untuk pipa. */
class FormulaComponentMaterial extends Model
{
    protected $fillable = ['formula_component_id', 'material_id', 'qty', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(FormulaComponent::class, 'formula_component_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
