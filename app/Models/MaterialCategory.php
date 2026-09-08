<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori bahan baku: pipa, plat, inlet, baut.
 */
class MaterialCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}
