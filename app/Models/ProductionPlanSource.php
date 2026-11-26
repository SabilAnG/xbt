<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pembagian buat/beli satu barang di satu rencana: buy_qty dibeli, sisa kekurangan dibuat. */
class ProductionPlanSource extends Model
{
    protected $fillable = ['production_plan_id', 'material_id', 'buy_qty'];

    protected function casts(): array
    {
        return ['buy_qty' => 'decimal:3'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
