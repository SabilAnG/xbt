<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Nota pembelian. Stok dan kas baru bergerak saat status menjadi `posted`.
 */
class Purchase extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'posted' => 'Dibukukan',
    ];

    protected $fillable = [
        'invoice_number', 'purchased_at', 'supplier_name', 'wallet_id',
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

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
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

    /**
     * Hitung ulang total dari baris-barisnya.
     */
    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('subtotal');

        $this->forceFill([
            'subtotal' => $subtotal,
            'total' => $subtotal - (float) $this->discount + (float) $this->shipping_cost,
        ])->save();
    }
}
