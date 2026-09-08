<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Nota penjualan. Stok dan kas baru bergerak saat status menjadi `posted`.
 */
class Sale extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'posted' => 'Dibukukan',
    ];

    protected $fillable = [
        'invoice_number', 'sold_at', 'customer_name', 'customer_phone', 'wallet_id',
        'subtotal', 'discount', 'shipping_cost', 'total', 'total_cost',
        'status', 'posted_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'date',
            'posted_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
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

    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('subtotal');

        $this->forceFill([
            'subtotal' => $subtotal,
            'total' => $subtotal - (float) $this->discount + (float) $this->shipping_cost,
        ])->save();
    }

    /** Laba kotor: total jual dikurangi modal yang dibekukan saat pembukuan. */
    public function grossProfit(): float
    {
        return (float) $this->total - (float) $this->total_cost;
    }
}
