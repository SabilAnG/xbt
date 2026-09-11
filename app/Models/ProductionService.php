<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu jasa produksi berikut tarifnya.
 *
 * Biaya membuat knalpot tidak berhenti di bahan: ada chrome, poles, las, dan
 * bending yang ditagih per satuan. Daftarnya ditaruh di sini supaya tarif yang
 * naik cukup diubah di satu tempat.
 *
 * Formula memanggil tarif di sini, tidak menyalinnya. Ongkos chrome yang naik
 * cukup diubah sekali, dan seluruh resep yang memakainya ikut menyesuaikan.
 */
class ProductionService extends Model
{
    protected $fillable = [
        'name', 'slug', 'unit', 'rate',
        'description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected $attributes = [
        'unit' => 'unit',
        'rate' => 0,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /** Baris formula yang memakai jasa ini — penjaga saat hendak dihapus. */
    public function formulaServices(): HasMany
    {
        return $this->hasMany(FormulaService::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** "Rp150.000 / unit" — cara orang menyebut tarifnya. */
    public function displayRate(): string
    {
        return 'Rp'.number_format((float) $this->rate, 0, ',', '.').' / '.($this->unit ?: 'unit');
    }

    /** Biaya jasa ini untuk sekian satuan. */
    public function costFor(float $qty): float
    {
        return $qty * (float) $this->rate;
    }
}
