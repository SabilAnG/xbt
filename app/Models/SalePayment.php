<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uang masuk untuk satu nota penjualan (bisa dicicil, tiap cicilan ke dompetnya). */
class SalePayment extends Model
{
    protected $fillable = ['sale_id', 'wallet_id', 'paid_at', 'amount', 'notes'];

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
