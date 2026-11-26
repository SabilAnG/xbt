<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nota produksi. Draf belum menyentuh stok; saat dibukukan bahan dipotong
 * dari gudang, pipa dipotong dari batang/sisa, dan knalpot jadi masuk ke
 * Finish Good dengan HPP sebenarnya.
 */
class Production extends Model
{
    public const DRAFT = 'draft';

    public const POSTED = 'posted';

    public const INTERNAL = 'internal';

    public const VENDOR = 'vendor';

    protected $fillable = [
        'production_number', 'produced_at', 'formula_id', 'item_id', 'production_plan_line_id', 'qty', 'mode', 'vendor', 'process',
        'service_cost_per_unit', 'received_qty', 'worker',
        'status', 'material_cost', 'waste_cost', 'other_cost', 'service_cost', 'total_cost', 'hpp_per_unit', 'posted_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'produced_at' => 'date',
            'qty' => 'decimal:3',
            'service_cost_per_unit' => 'decimal:2',
            'received_qty' => 'decimal:3',
            'material_cost' => 'decimal:2',
            'waste_cost' => 'decimal:2',
            'other_cost' => 'decimal:2',
            'service_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'hpp_per_unit' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function planLine(): BelongsTo
    {
        return $this->belongsTo(ProductionPlanLine::class, 'production_plan_line_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ProductionUsage::class);
    }

    public function cuts(): HasMany
    {
        return $this->hasMany(ProductionCut::class)->orderBy('sequence');
    }

    /** Tagihan jasa komponen (mis. bending P1) yang dibuat saat produksi dibukukan. */
    public function serviceBills(): HasMany
    {
        return $this->hasMany(MaterialPurchase::class)->where('kind', 'jasa');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ProductionReceipt::class)->orderBy('received_at')->orderBy('id');
    }

    public function isVendor(): bool
    {
        return $this->mode === self::VENDOR;
    }

    /** Jumlah yang masih di vendor (sudah dikirim, belum diterima). */
    public function outstandingQty(): float
    {
        return $this->isVendor() && $this->isPosted() ? max((float) $this->qty - (float) $this->received_qty, 0) : 0;
    }

    /** Nilai bahan yang sedang di vendor. */
    public function outstandingValue(): float
    {
        return $this->outstandingQty() * (float) $this->hpp_per_unit;
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', self::POSTED);
    }

    public function isPosted(): bool
    {
        return $this->status === self::POSTED;
    }

    public function statusLabel(): string
    {
        if (! $this->isPosted()) {
            return 'DRAF';
        }

        if ($this->isVendor()) {
            return $this->outstandingQty() > 0 ? 'DI VENDOR' : 'SELESAI';
        }

        return 'DIBUKUKAN';
    }
}
