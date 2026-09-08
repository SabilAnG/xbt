<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jenis pengeluaran: Jasa, Barang, Operasional, Gaji, dst.
 */
class ExpenseType extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }
}
