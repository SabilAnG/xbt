<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vendor / supplier bahan baku produksi.
 */
class Vendor extends Model
{
    protected $fillable = ['name', 'phone', 'address', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(MaterialPurchase::class);
    }
}
