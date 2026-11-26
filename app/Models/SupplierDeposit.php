<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Uang muka (DP) ke supplier/vendor sebelum tagihannya ada; nanti dipakai membayar tagihan. */
class SupplierDeposit extends Model
{
    use RecordsActivity;

    public function activityLabel(): string
    {
        return 'Uang muka '.($this->supplier?->name ?? '').' · '.rupiah($this->amount);
    }

    protected $fillable = ['supplier_id', 'wallet_id', 'paid_at', 'amount', 'notes'];

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** Pembayaran tagihan yang memakai uang muka ini. */
    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function remaining(): float
    {
        return (float) $this->amount - (float) $this->payments()->sum('amount');
    }
}
