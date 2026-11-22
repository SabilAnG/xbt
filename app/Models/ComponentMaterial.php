<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris bahan penyusun: butuh berapa bahan baku untuk 1 komponen. */
class ComponentMaterial extends Model
{
    protected $fillable = ['component_id', 'material_id', 'qty'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
