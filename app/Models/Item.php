<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Barang di gudang. `stock` adalah cache dari stock_movements.
 */
class Item extends Model
{
    protected $fillable = [
        'sku', 'name', 'item_category_id', 'item_type_id', 'product_id',
        'unit', 'cost_price', 'sell_price', 'stock', 'min_stock',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'stock' => 'decimal:2',
            'min_stock' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }

    /** Tautan opsional ke katalog website. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function motorcycleModels(): BelongsToMany
    {
        return $this->belongsToMany(MotorcycleModel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('moved_at');
    }

    /** Stok sesungguhnya menurut kartu stok. */
    public function computedStock(): float
    {
        return (float) $this->movements()->sum('qty_in') - (float) $this->movements()->sum('qty_out');
    }

    public function recalculateStock(): void
    {
        $this->forceFill(['stock' => $this->computedStock()])->save();
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }

    public function stockValue(): float
    {
        return (float) $this->stock * (float) $this->cost_price;
    }
}
