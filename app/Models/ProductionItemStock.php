<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stok satu barang di satu gudang.
 *
 * Rincian dari `production_items.stock`, yang menyimpan totalnya. Keduanya
 * sama-sama turunan kartu stok, jadi tidak bisa berselisih kecuali ada yang
 * menulis langsung ke tabel — dan itu yang dijaga jangan sampai terjadi.
 */
class ProductionItemStock extends Model
{
    protected $fillable = ['production_item_id', 'warehouse_id', 'qty'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductionItem::class, 'production_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
