<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Komponen dalam satu bagian: Pureng, Plendes, P1, P2, Tabung, … */
class FormulaComponent extends Model
{
    protected $fillable = ['formula_part_id', 'name', 'qty', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(FormulaPart::class, 'formula_part_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(FormulaComponentMaterial::class)->orderBy('sort_order')->orderBy('id');
    }
}
