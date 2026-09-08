<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pemakaian satu mesin di dalam formula, dalam menit.
 */
class FormulaMachine extends Model
{
    protected $fillable = [
        'formula_id', 'machine_id', 'bom_group', 'minutes', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['minutes' => 'decimal:2'];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function subtotal(): float
    {
        return $this->machine?->costForMinutes((float) $this->minutes) ?? 0.0;
    }
}
