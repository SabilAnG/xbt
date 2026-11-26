<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Master data motor: kategori, brand, type motor, dan varian knalpot. */
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

    protected $fillable = ['kind', 'name', 'sort_order'];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** @return Collection<string, string> nama => nama, siap untuk dropdown. */
    public static function choices(string $kind): Collection
    {
        return static::query()->where('kind', $kind)->ordered()->pluck('name')->mapWithKeys(fn ($name) => [$name => $name]);
    }

    /**
     * Nama baku untuk nilai yang diketik: dicocokkan tanpa beda huruf besar-kecil,
     * dan dicatat ke master bila belum ada.
     */
    public static function remember(string $kind, ?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $existing = static::query()->where('kind', $kind)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        return ($existing ?? static::create([
            'kind' => $kind,
            'name' => $name,
            'sort_order' => (int) static::query()->where('kind', $kind)->max('sort_order') + 1,
        ]))->name;
    }

    /** Jumlah formula yang memakai pilihan ini. */
    public function usage(): int
    {
        return Formula::query()->where(self::COLUMNS[$this->kind], $this->name)->count();
    }
}
