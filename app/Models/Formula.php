<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Resep satu knalpot untuk satu type motor.
 *
 * Isinya daftar komponen, dan di tiap komponen dijawab dua hal yang memang
 * berbeda tiap motor: bahannya apa, dan berapa ukurannya. Header Mio dan NMAX
 * memakai komponen yang sama persis, tapi panjang pipanya tidak.
 *
 * Angkanya diturunkan saat dipanggil, tidak disimpan: begitu harga pipa naik
 * di master, seluruh formula yang memakainya ikut menyesuaikan tanpa perlu
 * disentuh satu per satu.
 */
class Formula extends Model
{
    protected $fillable = [
        'code', 'name', 'motorcycle_model_id',
        'output_qty', 'output_unit', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'output_qty' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    protected $attributes = [
        'output_qty' => 1,
        'output_unit' => 'set',
        'is_active' => true,
    ];

    public function motorcycleModel(): BelongsTo
    {
        return $this->belongsTo(MotorcycleModel::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FormulaLine::class)->orderBy('sort_order');
    }

    // ------------------------------------------------------------- susunan

    /**
     * Baris dikelompokkan per bagian knalpot, urut sesuai daftar komponen —
     * begitu cara orang bengkel membacanya.
     *
     * @return Collection<string, Collection<int, FormulaLine>>
     */
    public function linesByBagian(): Collection
    {
        return $this->lines
            ->loadMissing('component.parent')
            ->sortBy(fn (FormulaLine $l) => [
                $l->component?->parent?->sort_order ?? 0,
                $l->component?->sort_order ?? 0,
            ])
            ->groupBy(fn (FormulaLine $l) => $l->component?->parent?->name
                ?? $l->component?->name
                ?? 'Lain-lain');
    }

    /** Komponen yang bahannya belum ditentukan — resep yang belum siap pakai. */
    public function lineTanpaBahan(): Collection
    {
        return $this->lines->whereNull('production_item_id');
    }

    public function siap(): bool
    {
        return $this->lines->isNotEmpty() && $this->lineTanpaBahan()->isEmpty();
    }

    // ----------------------------------------------------------------- biaya

    /** Biaya bahan untuk satu kali resep. */
    public function materialCost(): float
    {
        return $this->lines->sum(fn (FormulaLine $line) => $line->subtotal());
    }

    /** Modal bahan per unit keluaran. */
    public function materialCostPerUnit(): float
    {
        $out = (float) $this->output_qty;

        return $out > 0 ? $this->materialCost() / $out : 0.0;
    }

    /** Nama yang tidak ambigu: "Racing Standar — Yamaha Mio". */
    public function fullName(): string
    {
        $motor = $this->motorcycleModel?->fullName();

        return $motor ? $this->name.' — '.$motor : $this->name;
    }
}
