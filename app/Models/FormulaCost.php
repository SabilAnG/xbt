<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris biaya jasa di dalam formula.
 */
class FormulaCost extends Model
{
    protected $fillable = [
        'formula_id', 'cost_component_id', 'bom_group', 'qty', 'minutes', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'minutes' => 'decimal:2',
        ];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(CostComponent::class, 'cost_component_id');
    }

    /**
     * Komponen bertarif per jam dihitung dari menitnya; komponen borongan
     * dihitung dari jumlahnya. Jadi "potong pipa 10 menit @ Rp25.000/jam"
     * dan "poles Rp35.000 borongan" bisa hidup berdampingan di satu formula.
     */
    public function subtotal(): float
    {
        if (! $this->component) {
            return 0.0;
        }

        return $this->component->costForMinutes(
            (float) $this->minutes,
            (float) $this->qty
        );
    }

    /** "10 menit @ Rp25.000/jam" atau "1 x Rp35.000/unit" */
    public function inputLabel(): string
    {
        if (! $this->component) {
            return '-';
        }

        $trim = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');

        return $this->component->isHourly()
            ? $trim($this->minutes).' menit @ '.$this->component->displayRate()
            : $trim($this->qty).' x '.$this->component->displayRate();
    }
}
