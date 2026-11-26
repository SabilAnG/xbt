<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Barang di gudang. `stock` adalah cache dari stock_movements.
 */
class Item extends Model
{
    protected $fillable = [
        'sku', 'name', 'model_motor', 'finishing', 'variant', 'size', 'brand',
        'item_category_id', 'item_type_id', 'product_id',
        'unit', 'cost_price', 'sell_price', 'sell_price_online', 'opening_stock', 'stock', 'min_stock',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'sell_price_online' => 'decimal:2',
            'opening_stock' => 'decimal:2',
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

    /** Formula yang menghasilkan produk ini. */
    public function formula(): HasOne
    {
        return $this->hasOne(Formula::class);
    }

    /**
     * Status stok seperti di buku kerja: HABIS bila tidak bersisa, MENIPIS bila
     * sudah di batas minimum, selain itu AMAN.
     */
    public function stockStatus(): string
    {
        return stock_status((float) $this->stock, (float) $this->min_stock);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('moved_at');
    }

    /** SISA STOK = stok awal + masuk − keluar (kolom O di sheet Stok Produk). */
    public function computedStock(): float
    {
        return (float) $this->opening_stock + (float) $this->movements()->sum('qty_in') - (float) $this->movements()->sum('qty_out');
    }

    /** Hitung ulang stok dan saldo berjalan kartu stok, urut tanggal. */
    public function recalculateStock(): void
    {
        $balance = (float) $this->opening_stock;

        foreach (StockMovement::where('item_id', $this->id)->orderBy('moved_at')->orderBy('id')->get() as $movement) {
            $balance += (float) $movement->qty_in - (float) $movement->qty_out;

            if ((float) $movement->balance_after !== round($balance, 2)) {
                $movement->forceFill(['balance_after' => $balance])->save();
            }
        }

        $this->forceFill(['stock' => $balance])->save();
    }

    /** Qty diproduksi (kolom "+ Produksi"). */
    public function producedQty(): float
    {
        return (float) $this->movements()->where('type', 'production')->sum('qty_in');
    }

    /** Qty terjual bersih, retur mengurangi (kolom "− Terjual"). */
    public function soldQty(): float
    {
        return (float) $this->movements()->where('type', 'sale')->sum('qty_out')
            - (float) $this->movements()->where('type', 'sale')->sum('qty_in');
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }

    /** Nilai stok (HPP) = sisa × HPP, termasuk bila sisa minus — sama dengan Excel. */
    public function stockValue(): float
    {
        return (float) $this->stock * (float) $this->cost_price;
    }

    /** Harga Daftar (acuan): harga offline untuk saluran Offline, selain itu harga online. */
    public function listPriceFor(?string $channel): float
    {
        return (float) ($channel === 'Offline' ? $this->sell_price : $this->sell_price_online);
    }
}
