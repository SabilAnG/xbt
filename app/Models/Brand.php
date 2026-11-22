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

    /** Baris resep yang menyebut merek ini. */
    public function formulaLines(): Builder
    {
        return FormulaMaterial::query()->where(fn ($query) => $query
            ->where('brand', $this->name)
            ->orWhere('brand', 'like', $this->name.',%')
            ->orWhere('brand', 'like', '%,'.$this->name)
            ->orWhere('brand', 'like', '%,'.$this->name.',%'));
    }

    public function isInUse(): bool
    {
        return $this->items()->exists() || $this->formulaLines()->exists();
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

            $this->formulaLines()->get()->each(function (FormulaMaterial $line) use ($old, $newName) {
                $line->brand = collect(explode(',', $line->brand))
                    ->map(fn ($name) => trim($name) === $old ? $newName : trim($name))
                    ->implode(',');
                $line->saveQuietly();
            });

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
