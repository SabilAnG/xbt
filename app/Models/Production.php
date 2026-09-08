<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Order produksi: menjalankan sebuah formula sebanyak `batch_qty` kali.
 *
 * Biaya dibekukan saat pembukuan sehingga HPP historis tetap utuh walau harga
 * bahan berubah kemudian.
 */
class Production extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'posted' => 'Dibukukan',
    ];

    protected $fillable = [
        'production_number', 'produced_at', 'formula_id', 'production_plan_id', 'warehouse_id',
        'batch_qty', 'output_qty',
        'material_cost', 'service_cost', 'machine_cost', 'total_minutes',
        'overhead_cost', 'total_cost', 'hpp_per_unit',
        'status', 'posted_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'produced_at' => 'date',
            'posted_at' => 'datetime',
            'batch_qty' => 'decimal:3',
            'output_qty' => 'decimal:3',
            'material_cost' => 'decimal:2',
            'service_cost' => 'decimal:2',
            'machine_cost' => 'decimal:2',
            'total_minutes' => 'decimal:2',
            'overhead_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'hpp_per_unit' => 'decimal:2',
        ];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }

    /** Rencana yang melahirkan nota ini, bila dibuat lewat menu Rencana Produksi. */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProductionMaterial::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ProductionCost::class);
    }

    public function machines(): HasMany
    {
        return $this->hasMany(ProductionMachine::class);
    }

    public function materialMovements(): MorphMany
    {
        return $this->morphMany(MaterialMovement::class, 'source');
    }

    /**
     * Berapa unit yang dituju nota ini.
     *
     * Nota yang sudah diisi dari formula punya output_qty; yang masih kosong
     * dihitung dari jumlah resep dikali hasil per resep.
     */
    public function targetUnit(): float
    {
        return (float) $this->output_qty
            ?: (float) $this->batch_qty * (float) ($this->formula->output_qty ?? 1);
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
