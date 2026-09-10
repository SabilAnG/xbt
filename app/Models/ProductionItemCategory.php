<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jenis barang produksi: Pipa, Plat, Hardware.
 *
 * Hanya untuk mengelompokkan saat mencari. Sumber pengadaan dan peran di
 * produk punya kolomnya sendiri di ProductionItem.
 */
class ProductionItemCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionItem::class);
    }
}
