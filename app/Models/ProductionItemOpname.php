<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Sesi hitung fisik barang produksi.
 *
 * Juga jalan masuk stok pertama: kolom stok di master sengaja dikunci, jadi
 * mengisinya dilakukan lewat opname supaya angka awal pun punya jejak.
 */
class ProductionItemOpname extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'posted' => 'Dibukukan',
    ];

    protected $fillable = [
        'opname_number', 'opname_date', 'counted_by', 'status', 'posted_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opname_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    protected $attributes = ['status' => 'draft'];

    public function items(): HasMany
    {
        return $this->hasMany(ProductionItemOpnameItem::class, 'production_item_opname_id');
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(ProductionItemMovement::class, 'source');
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    /** Berapa baris yang selisih antara catatan dan hitungan fisik. */
    public function discrepancyCount(): int
    {
        return $this->items()->where('difference', '!=', 0)->count();
    }
}
