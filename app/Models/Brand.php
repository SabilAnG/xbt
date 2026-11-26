<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Merek produk knalpot (XBT, FD, ...).
 *
 * Produk menyimpan nama merek di items.brand; baris resep menyimpan satu atau
 * beberapa nama merek dipisah koma di formula_materials.brand.
 */
class Brand extends Model
{
    protected $fillable = ['name', 'code', 'color', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'brand', 'name');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function isInUse(): bool
    {
        return $this->items()->exists();
    }

    /**
     * Ganti nama merek dan semua data yang memakainya dalam satu transaksi.
     */
    public function rename(string $newName): void
    {
        $old = $this->getOriginal('name');

        if ($old === $newName) {
            return;
        }

        DB::transaction(function () use ($old, $newName) {
            Item::where('brand', $old)->update(['brand' => $newName]);

            $this->forceFill(['name' => $newName])->save();
        });
    }

    /**
     * @return Collection<int, string>
     */
    public static function names(): Collection
    {
        return static::query()->ordered()->pluck('name');
    }
}
