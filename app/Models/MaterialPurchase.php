<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Nota pembelian bahan: tanggal, toko, siapa yang belanja. Tiap baris masuk ke
 * gudang tujuan yang dipilih (default gudang asal bahan); pipa
 * masuk sebagai batang-batang utuh. Pembayaran bisa dicicil — sisanya hutang.
 */
class MaterialPurchase extends Model
{
    use RecordsActivity;

    public function activityLabel(): string
    {
        return ($this->kind === 'jasa' ? 'Tagihan jasa ' : 'Pembelian ').$this->invoice_number.($this->supplier ? ' · '.$this->supplier : '').' · '.rupiah($this->total);
    }

    /** @return list<string> */
    public function activityFields(): array
    {
        return ['supplier', 'due_date', 'notes'];
    }

    protected $fillable = [
        'invoice_number', 'kind', 'purchased_at', 'due_date', 'supplier_id', 'supplier', 'supplier_location', 'supplier_invoice', 'buyer', 'production_plan_id', 'production_id', 'total', 'paid_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'due_date' => 'date',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialPurchaseItem::class);
    }

    public function supplierRecord(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class)->orderBy('returned_at')->orderBy('id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class)->orderBy('received_at')->orderBy('id');
    }

    /** Pesanan yang masih menunggu barang datang. */
    public function scopeAwaitingGoods(Builder $query): Builder
    {
        return $query->where('kind', '!=', 'jasa')->whereHas('items', fn (Builder $query) => $query->whereRaw('qty - received_qty - cancelled_qty > 0.0005'));
    }

    public function outstandingQty(): float
    {
        return (float) $this->items->sum(fn (MaterialPurchaseItem $line) => $line->outstandingQty());
    }

    /** BELUM DITERIMA / DITERIMA SEBAGIAN / DITERIMA — tagihan jasa tidak punya barang. */
    public function receiveStatus(): ?string
    {
        if ($this->isService()) {
            return null;
        }

        $received = (float) $this->items->sum('received_qty');

        return match (true) {
            $this->outstandingQty() <= 0 => 'DITERIMA',
            $received > 0 => 'DITERIMA SEBAGIAN',
            default => 'BELUM DITERIMA',
        };
    }

    /** Total tagihan = (dipesan − dibatalkan) × harga. */
    public function refreshTotal(): void
    {
        $this->forceFill(['total' => (float) $this->items()->get()->sum(fn (MaterialPurchaseItem $line) => ((float) $line->qty - (float) $line->cancelled_qty - (float) $line->returned_qty) * (float) $line->unit_price)])->save();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class)->orderBy('paid_at')->orderBy('id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(MaterialMovement::class, 'source');
    }

    /** Tagihan jasa vendor dari penerimaan produksi. */
    public function isService(): bool
    {
        return $this->kind === 'jasa';
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(ProductionReceipt::class);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereColumn('paid_amount', '<', 'total');
    }

    /** Jatuh tempo bawaan dari termin bayar supplier (bila belum diisi). */
    protected static function booted(): void
    {
        static::creating(function (MaterialPurchase $purchase) {
            if (! $purchase->due_date && $purchase->supplier_id && ($days = Supplier::whereKey($purchase->supplier_id)->value('payment_term_days'))) {
                $purchase->due_date = Carbon::parse($purchase->purchased_at)->addDays((int) $days)->toDateString();
            }
        });
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->payable() > 0 && $this->due_date->lt(today());
    }

    /** Belum lunas dan jatuh tempo dalam $days hari ke depan (termasuk yang sudah lewat). */
    public function scopeDueWithin(Builder $query, int $days = 3): Builder
    {
        return $query->unpaid()->whereNotNull('due_date')->whereDate('due_date', '<=', today()->addDays($days));
    }

    public function payable(): float
    {
        return max((float) $this->total - (float) $this->paid_amount, 0);
    }

    public function paymentStatus(): string
    {
        return $this->payable() > 0 ? 'BELUM LUNAS' : 'LUNAS';
    }

    public function refreshPaidAmount(): void
    {
        $this->forceFill(['paid_amount' => (float) $this->payments()->sum('amount')])->save();
    }
}
