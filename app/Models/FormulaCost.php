<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ongkos per knalpot di luar bahan: jasa las, bending, finishing, packing. */
class FormulaCost extends Model
{
    protected $fillable = ['formula_id', 'name', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }
}
