<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MotorcycleBrand extends Model
{
    protected $fillable = ['name', 'slug', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function models(): HasMany
    {
        return $this->hasMany(MotorcycleModel::class);
    }
}
