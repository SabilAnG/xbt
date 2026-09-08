<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dompet: kas laci, rekening bank, e-wallet.
 */
class Wallet extends Model
{
    public const TYPES = [
        'cash' => 'Kas / Tunai',
        'bank' => 'Rekening Bank',
        'ewallet' => 'E-Wallet',
    ];

    protected $fillable = [
        'name', 'type', 'account_number', 'holder_name',
        'opening_balance', 'current_balance', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->orderByDesc('occurred_at');
    }

    /**
     * Saldo sesungguhnya, dihitung dari mutasi — bukan dari kolom cache.
     */
    public function computedBalance(): float
    {
        $in = (float) $this->transactions()->where('direction', 'in')->sum('amount');
        $out = (float) $this->transactions()->where('direction', 'out')->sum('amount');

        return (float) $this->opening_balance + $in - $out;
    }

    public function recalculateBalance(): void
    {
        $this->forceFill(['current_balance' => $this->computedBalance()])->save();
    }
}
