<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('BB-####')),
            'name' => fake()->words(2, true),
            'warehouse_id' => fn () => Warehouse::forType(Warehouse::RAW)->id,
            'measure' => Material::UNIT,
            'unit' => 'Pcs',
            'last_price' => 1_000,
            'is_active' => true,
        ];
    }

    /** Pipa batangan: harga per batang, panjang batang, batas sisa pakai. */
    public function pipe(float $barLength = 600, float $pricePerBar = 300_000, float $minReusable = 5): static
    {
        return $this->state(fn () => [
            'measure' => Material::LENGTH,
            'unit' => 'Batang',
            'bar_length_cm' => $barLength,
            'min_reusable_cm' => $minReusable,
            'last_price' => $pricePerBar,
        ]);
    }

    /** Barang beli jadi di Gudang Setengah Jadi (pureng, plendes). */
    public function semiFinished(float $price = 10_000): static
    {
        return $this->state(fn () => [
            'warehouse_id' => Warehouse::forType(Warehouse::SEMI)->id,
            'last_price' => $price,
        ]);
    }
}
