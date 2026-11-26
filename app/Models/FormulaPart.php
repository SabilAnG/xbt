<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bagian knalpot di formula: Leheran, Silincer, atau bagian lain. */
class FormulaPart extends Model
{
    protected $fillable = ['formula_id', 'name', 'sort_order'];

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(FormulaComponent::class)->orderBy('sort_order')->orderBy('id');
    }
}
