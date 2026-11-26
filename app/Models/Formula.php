<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

/**
 * Formula satu knalpot, mis. "Mio · Racing Sarangan Tengah", atau satu barang
 * setengah jadi yang dibuat sendiri / lewat vendor, mis. "Leheran Mio bending".
 *
 * Tersusun dari bagian (Leheran, Silincer), tiap bagian berisi komponen
 * (Pureng, Plendes, P1, P2, …), dan tiap komponen memakai bahan dari gudang
 * dengan jumlahnya — untuk pipa, panjang potong dalam cm. Harga bahan ikut
 * terbawa dari master bahan, sehingga HPP perkiraan langsung terlihat.
 */
class Formula extends Model
{
    public const MOTOR_CATEGORIES = [
        'matic' => 'Matic',
        'bebek' => 'Bebek',
        'sport' => 'Sport',
        'lainnya' => 'Lainnya',
    ];

    /** Saran type motor bila belum ada formula. */
    public const MOTOR_TYPES = ['Mio', 'Beat', 'Vario', 'Scoopy', 'NMAX', 'PCX', 'Aerox', 'Lexi', 'Fino'];

    /** Bagian yang otomatis ada di formula knalpot baru. */
    public const DEFAULT_PARTS = ['Leheran', 'Silincer', 'Aksesoris'];

    public const KNALPOT = 'knalpot';

    public const SEMI_FINISHED = 'setengah_jadi';

    public const OUTPUTS = [
        self::KNALPOT => 'Knalpot jadi (masuk Finish Good)',
        self::SEMI_FINISHED => 'Barang setengah jadi (masuk Gudang Setengah Jadi)',
    ];

    protected $fillable = [
        'code', 'name', 'motor_category', 'motor_brand', 'motor_type', 'variant', 'output_type', 'item_id', 'output_material_id', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Produk jadi (Finish Good) hasil formula ini. */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Barang setengah jadi hasil formula ini (mis. Leheran Mio bending). */
    public function outputMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'output_material_id');
    }

    public function isSemiFinished(): bool
    {
        return $this->output_type === self::SEMI_FINISHED;
    }

    /** Nama hasil produksi: produk jadi atau barang setengah jadi. */
    public function outputName(): string
    {
        return (string) ($this->isSemiFinished() ? $this->outputMaterial?->label() : $this->item?->name);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(FormulaPart::class)->orderBy('sort_order')->orderBy('id');
    }

    public function components(): HasManyThrough
    {
        return $this->hasManyThrough(FormulaComponent::class, FormulaPart::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(FormulaCost::class)->orderBy('sort_order')->orderBy('id');
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function motorCategoryLabel(): string
    {
        // Data lama menyimpan kunci (matic); data baru menyimpan nama dari master.
        return self::MOTOR_CATEGORIES[$this->motor_category] ?? (string) $this->motor_category;
    }

    /** Muat seluruh struktur formula sekali jalan. */
    public function loadStructure(): static
    {
        return $this->loadMissing(['parts.components.materials.material.stocks', 'costs']);
    }

    /**
     * Kebutuhan bahan untuk membuat sejumlah knalpot.
     *
     * Untuk pipa, `cuts` berisi panjang tiap potongan (cm) — satu potongan per
     * komponen per knalpot — supaya bisa dihitung dari batang/sisa mana
     * potongan itu diambil.
     *
     * @return Collection<int, array{part: string, component: string, material: Material, qty: float, cuts: list<float>, unit_cost: float, cost: float}>
     */
    public function requirements(float $units = 1): Collection
    {
        $this->loadStructure();
        $lines = collect();

        foreach ($this->parts as $part) {
            foreach ($part->components as $component) {
                foreach ($component->materials as $line) {
                    $material = $line->material;
                    $pieces = (float) $component->qty * $units;
                    $qty = (float) $line->qty * $pieces;
                    $cuts = [];

                    if ($material->isPipe()) {
                        $count = (int) round($pieces);
                        $cuts = array_fill(0, max($count, 0), (float) $line->qty);
                    }

                    $lines->push([
                        'part' => $part->name,
                        'component' => $component->name,
                        'material' => $material,
                        'qty' => $qty,
                        'cuts' => $cuts,
                        'unit_cost' => $material->unitCost(),
                        'cost' => $qty * $material->unitCost(),
                    ]);
                }
            }
        }

        return $lines;
    }

    public function materialCostPerUnit(): float
    {
        return (float) $this->requirements()->sum('cost');
    }

    public function otherCostPerUnit(): float
    {
        return (float) $this->loadStructure()->costs->sum('amount');
    }

    /** HPP perkiraan per knalpot (bahan + ongkos, belum termasuk sisa yang terbuang). */
    public function estimatedHpp(): float
    {
        return $this->materialCostPerUnit() + $this->otherCostPerUnit();
    }

    /** Biaya bahan per bagian, untuk ringkasan formula. */
    public function costByPart(): Collection
    {
        return $this->requirements()->groupBy('part')->map(fn (Collection $lines) => (float) $lines->sum('cost'));
    }

    public function displayName(): string
    {
        return $this->name;
    }
}
