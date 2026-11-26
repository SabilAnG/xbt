<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Master data motor: kategori, brand, type motor (milik satu brand), dan varian knalpot. */
class MotorOption extends Model
{
    public const KINDS = [
        'kategori' => 'Kategori motor',
        'brand' => 'Brand motor',
        'type' => 'Type motor',
        'varian' => 'Varian knalpot',
    ];

    /** Kolom formula yang menyimpan tiap jenis. */
    public const COLUMNS = [
        'kategori' => 'motor_category',
        'brand' => 'motor_brand',
        'type' => 'motor_type',
        'varian' => 'variant',
    ];

    protected $fillable = ['kind', 'parent_id', 'name', 'sort_order'];

    /** Brand induk sebuah type motor. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Type motor milik sebuah brand. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** @return Collection<string, string> nama => nama, siap untuk dropdown. */
    public static function choices(string $kind): Collection
    {
        return static::query()->where('kind', $kind)->ordered()->pluck('name')->mapWithKeys(fn ($name) => [$name => $name]);
    }

    /** @return Collection<string, string> type => brand induknya. */
    public static function typeBrands(): Collection
    {
        return static::query()->where('kind', 'type')->whereNotNull('parent_id')->with('parent')->get()
            ->mapWithKeys(fn (self $type) => [$type->name => $type->parent?->name]);
    }

    public static function findByName(string $kind, ?string $name): ?self
    {
        $name = trim((string) $name);

        return $name === '' ? null : static::query()->where('kind', $kind)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
    }

    /**
     * Nama baku untuk nilai yang diketik: dicocokkan tanpa beda huruf besar-kecil,
     * dan dicatat ke master bila belum ada. Type motor yang belum punya brand
     * disambungkan ke brand yang diberikan.
     */
    public static function remember(string $kind, ?string $name, ?string $brand = null): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $parentId = $kind === 'type' && filled($brand) ? static::findByName('brand', static::remember('brand', $brand))?->id : null;
        $existing = static::findByName($kind, $name);

        if ($existing) {
            if ($parentId && ! $existing->parent_id) {
                $existing->update(['parent_id' => $parentId]);
            }

            return $existing->name;
        }

        return static::create([
            'kind' => $kind,
            'parent_id' => $parentId,
            'name' => $name,
            'sort_order' => (int) static::query()->where('kind', $kind)->max('sort_order') + 1,
        ])->name;
    }

    /** Jumlah formula yang memakai pilihan ini. */
    public function usage(): int
    {
        return Formula::query()->where(self::COLUMNS[$this->kind], $this->name)->count();
    }
}
