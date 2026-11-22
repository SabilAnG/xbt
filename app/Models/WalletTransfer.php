<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pemindahan uang antar dompet. */
class WalletTransfer extends Model
{
    protected $fillable = ['from_wallet_id', 'to_wallet_id', 'amount', 'transferred_at', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transferred_at' => 'date'];
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }
}
