<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris rencana: formula mana, mau berapa unit.
 */
class ProductionPlanLine extends Model
{
    protected $fillable = [
        'production_plan_id', 'formula_id', 'target_qty', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['target_qty' => 'decimal:3'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }

    /**
     * Berapa kali resep harus dijalankan.
     *
     * Dibulatkan ke atas: resep yang menghasilkan 2 set tidak bisa dijalankan
     * setengah kali untuk mendapat 1 set.
     */
    public function batchCount(): float
    {
        $per = max((float) ($this->formula->output_qty ?? 1), 0.001);

        return ceil((float) $this->target_qty / $per);
    }

    /** Unit yang benar-benar dihasilkan — bisa lebih dari target karena pembulatan batch. */
    public function actualOutput(): float
    {
        return $this->batchCount() * (float) ($this->formula->output_qty ?? 1);
    }

    public function hpp(): float
    {
        return $this->formula?->hppPerUnit() ?? 0.0;
    }

    public function productionCost(): float
    {
        return $this->hpp() * (float) $this->target_qty;
    }
}
