<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
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
    use RecordsActivity;

    public function activityLabel(): string
    {
        return 'Formula '.$this->name;
    }

    /** @return list<string> */
    public function activityFields(): array
    {
        return ['name', 'is_active'];
    }

    public const MOTOR_CATEGORIES = [
        'matic' => 'Matic',
        'bebek' => 'Bebek',
        'sport' => 'Sport',
        'lainnya' => 'Lainnya',
    ];

    /** Saran type motor bila belum ada formula. */
    public const MOTOR_TYPES = ['Mio', 'Beat', 'Vario', 'Scoopy', 'NMAX', 'PCX', 'Aerox', 'Lexi', 'Fino'];

    /** Bagian yang otomatis ada di formula knalpot baru. */
    /** Bagian knalpot; aksesoris adalah gudang bahan yang dipakai di dalam bagian ini. */
    public const DEFAULT_PARTS = ['Leheran', 'Silincer'];

    public const KNALPOT = 'knalpot';

    public const SEMI_FINISHED = 'setengah_jadi';

    public const OUTPUTS = [
        self::KNALPOT => 'Knalpot jadi (masuk Finish Good)',
        self::SEMI_FINISHED => 'Barang setengah jadi (masuk Gudang Setengah Jadi)',
    ];

    protected $fillable = [
        'code', 'name', 'motor_category', 'motor_brand', 'motor_type', 'variant', 'output_type', 'item_id', 'output_material_id', 'work_mode', 'vendor_service_id', 'vendor', 'process', 'service_cost', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'service_cost' => 'decimal:2'];
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

    /**
     * Ongkos formula yang dibayar borongan (lihat Data Ongkos).
     *
     * @return Collection<int, array{name: string, rate: float}>
     */
    public function piecework(): Collection
    {
        $modes = CostOption::query()->get(['name', 'payment_mode'])->mapWithKeys(fn (CostOption $option) => [mb_strtolower($option->name) => $option->payment_mode]);

        return $this->loadStructure()->costs
            ->filter(fn (FormulaCost $cost) => ($modes[mb_strtolower($cost->name)] ?? 'gaji') === 'borongan' && (float) $cost->amount > 0)
            ->map(fn (FormulaCost $cost) => ['name' => $cost->name, 'rate' => (float) $cost->amount])
            ->values();
    }

    public function otherCostPerUnit(): float
    {
        return (float) $this->loadStructure()->costs->sum('amount');
    }

    /** Barang setengah jadi ini dikerjakan vendor (mis. bending), bukan di bengkel sendiri. */
    public function isVendorWork(): bool
    {
        return $this->isSemiFinished() && $this->work_mode === 'vendor';
    }

    /** Jasa vendor per unit: seluruh barang (formula setengah jadi) + komponen yang dijasakan. */
    public function serviceCostPerUnit(): float
    {
        return ($this->isVendorWork() ? (float) $this->service_cost : 0.0) + $this->componentServiceCostPerUnit();
    }

    public function componentServiceCostPerUnit(): float
    {
        return (float) $this->loadStructure()->parts->flatMap->components->sum(fn (FormulaComponent $component) => $component->serviceCostPerUnit());
    }

    /**
     * Komponen yang dijasakan untuk sejumlah unit.
     *
     * @return Collection<int, array{part: string, component: string, vendor: string, process: ?string, qty: float, rate: float, cost: float}>
     */
    public function componentServices(float $units = 1): Collection
    {
        return $this->loadStructure()->parts->flatMap(fn (FormulaPart $part) => $part->components
            ->filter(fn (FormulaComponent $component) => $component->isVendorWork())
            ->map(fn (FormulaComponent $component) => [
                'part' => $part->name,
                'component' => $component->name,
                'vendor' => $component->vendor,
                'process' => $component->process,
                'qty' => (float) $component->qty * $units,
                'rate' => (float) $component->service_cost,
                'cost' => (float) $component->qty * $units * (float) $component->service_cost,
            ]))->values();
    }

    /** HPP perkiraan per unit (bahan + ongkos + jasa vendor, belum termasuk sisa yang terbuang). */
    public function estimatedHpp(): float
    {
        return $this->materialCostPerUnit() + $this->otherCostPerUnit() + $this->serviceCostPerUnit();
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
