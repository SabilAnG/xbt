<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Nota pembelian bahan produksi.
 *
 * Stok dan kas baru bergerak saat status menjadi `posted` — sampai saat itu
 * notanya boleh diutak-atik sebebasnya tanpa meninggalkan jejak di mana pun.
 */
class ProductionPurchase extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'posted' => 'Dibukukan',
    ];

    protected $fillable = [
        'invoice_number', 'purchased_at', 'supplier_name',
        'warehouse_id', 'wallet_id',
        'subtotal', 'discount', 'shipping_cost', 'total',
        'status', 'posted_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'posted_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected $attributes = [
        'status' => 'draft',
        'discount' => 0,
        'shipping_cost' => 0,
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProductionPurchaseItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(ProductionItemMovement::class, 'source');
    }

    public function walletTransactions(): MorphMany
    {
        return $this->morphMany(WalletTransaction::class, 'source');
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    public function displayStatus(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Total selalu diturunkan dari barisnya, tidak pernah diketik. */
    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('subtotal');

        $this->forceFill([
            'subtotal' => $subtotal,
            'total' => $subtotal - (float) $this->discount + (float) $this->shipping_cost,
        ])->save();
    }
}
