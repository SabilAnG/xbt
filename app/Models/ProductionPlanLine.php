<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionPlanLine extends Model
{
    protected $fillable = ['production_plan_id', 'formula_id', 'qty', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }
}
