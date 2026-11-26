<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cache stok & nilai satu bahan di satu gudang (cm untuk pipa). */
class MaterialStock extends Model
{
    protected $fillable = ['material_id', 'warehouse_id', 'qty', 'value'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'value' => 'decimal:2'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
