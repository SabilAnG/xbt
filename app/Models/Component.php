<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Komponen knalpot (leher, tabung silencer, sarangan, moncong, …): barang
 * setengah jadi yang dibuat dari bahan baku lalu dirakit jadi knalpot.
 */
class Component extends Model
{
    public const GROUPS = [
        'leher' => 'Leher / Header',
        'silencer' => 'Silencer / Tabung',
        'ujung' => 'Moncong / Ujung',
        'dudukan' => 'Breket & Dudukan',
        'aksesoris' => 'Aksesoris & Kosmetik',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = ['code', 'name', 'group', 'unit', 'min_stock', 'cost_price', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'cost_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ComponentMaterial::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ComponentMovement::class)->orderByDesc('moved_at')->orderByDesc('id');
    }

    public function groupLabel(): string
    {
        return self::GROUPS[$this->group] ?? $this->group;
    }

    /** Biaya bahan baku untuk membuat 1 komponen (qty × harga rata-rata bahan). */
    public function materialCost(): float
    {
        return (float) $this->materials->sum(fn (ComponentMaterial $line) => (float) $line->qty * (float) $line->material?->cost_price);
    }

    /**
     * Harga per unit: dari bahan penyusun bila diisi, selain itu harga
     * rata-rata pembelian komponen jadi.
     */
    public function unitCost(): float
    {
        return $this->materials->isNotEmpty() ? $this->materialCost() : (float) $this->cost_price;
    }

    public function stockValue(): float
    {
        return (float) $this->stock * $this->unitCost();
    }

    public function stockStatus(): string
    {
        return stock_status(round((float) $this->stock, 2), (float) $this->min_stock);
    }

    /** Hitung ulang stok dan saldo berjalan kartu stok komponen, urut tanggal. */
    public function recalculateStock(): void
    {
        $balance = 0.0;

        foreach (ComponentMovement::where('component_id', $this->id)->orderBy('moved_at')->orderBy('id')->get() as $movement) {
            $balance += (float) $movement->qty_in - (float) $movement->qty_out;

            if (round((float) $movement->balance_after, 3) !== round($balance, 3)) {
                $movement->forceFill(['balance_after' => $balance])->save();
            }
        }

        $this->forceFill(['stock' => $balance])->save();
    }
}
