<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu jasa yang dipakai sebuah formula.
 *
 * Menjawab satu hal saja: jasa ini dipakai berapa satuan. Tarifnya milik master
 * jasa, dan sengaja dibaca dari sana tiap kali dihitung.
 */
class FormulaService extends Model
{
    protected $fillable = [
        'formula_id', 'production_service_id', 'qty', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    protected $attributes = ['qty' => 1];

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ProductionService::class, 'production_service_id');
    }

    /** Biaya jasa ini untuk satu kali resep. */
    public function subtotal(): float
    {
        return $this->service?->costFor((float) $this->qty) ?? 0.0;
    }

    /** "12 titik" — banyaknya berikut satuan tarifnya. */
    public function displayQty(): string
    {
        $angka = rtrim(rtrim(number_format((float) $this->qty, 3, ',', '.'), '0'), ',');

        return $angka.' '.($this->service?->unit ?? 'unit');
    }
}
