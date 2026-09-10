<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jenis barang produksi: Pipa, Plat, Hardware, Bahan Penolong.
 *
 * Memegang peran dan sumber pengadaan untuk seluruh barang di bawahnya.
 * Seluruh pipa adalah bahan utama yang dibeli; menjawabnya sekali di sini jauh
 * lebih masuk akal daripada mengulanginya tiap menambah barang. Barang yang
 * menyimpang tetap bisa mengubahnya sendiri.
 */
class ProductionItemCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'source', 'role', 'default_warehouse_id',
        'description', 'is_active', 'sort_order',
    ];

    /** Disamakan dengan bawaan kolomnya — lihat catatan di ProductionItem. */
    protected $attributes = [
        'source' => 'beli',
        'role' => 'utama',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionItem::class);
    }

    /** Gudang tempat barang jenis ini biasanya disimpan. */
    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function displaySource(): string
    {
        return ProductionItem::SOURCES[$this->source] ?? $this->source;
    }

    public function displayRole(): string
    {
        return ProductionItem::ROLES[$this->role] ?? $this->role;
    }
}
