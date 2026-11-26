<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kategori bahan: Pipa, Plat, Pureng & Plendes, Baut, dll. */
class MaterialCategory extends Model
{
    protected $fillable = ['name', 'sort_order'];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}
