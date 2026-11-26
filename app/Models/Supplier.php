<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Master supplier: toko bahan dan vendor jasa produksi. */
class Supplier extends Model
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Cari supplier dari nama yang diketik; bila belum ada, langsung dibuat. */
    public static function resolve(?string $name): ?self
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return static::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? static::create(['name' => $name]);
    }

    public function payable(): float
    {
        return (float) $this->purchases()->unpaid()->get()->sum(fn (MaterialPurchase $purchase) => $purchase->payable());
    }
}
