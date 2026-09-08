<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Pengeluaran di luar pembelian barang. Kas bergerak saat status `posted`.
 */
class Expense extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'posted' => 'Dibukukan',
    ];

    protected $fillable = [
        'reference_number', 'spent_at', 'expense_category_id', 'wallet_id',
        'amount', 'description', 'paid_to', 'status', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'spent_at' => 'date',
            'posted_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
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
}
