<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Master Data Komponen: bagian formula dan komponen di dalam tiap bagian. */
class ComponentOption extends Model
{
    public const PART = 'bagian';

    public const COMPONENT = 'komponen';

    protected $fillable = ['kind', 'parent_id', 'name', 'default_for', 'sort_order'];

    public function part(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function scopeParts(Builder $query): Builder
    {
        return $query->where('kind', self::PART)->orderBy('sort_order')->orderBy('name');
    }

    /** Bagian otomatis untuk formula baru menurut hasilnya (knalpot / setengah_jadi). */
    public static function defaultParts(string $outputType): array
    {
        return static::query()->parts()->where('default_for', $outputType)->pluck('name')->all();
    }

    /** @return Collection<string, list<string>> nama bagian => nama komponennya. */
    public static function suggestions(): Collection
    {
        return static::query()->parts()->with('components')->get()
            ->mapWithKeys(fn (self $part) => [$part->name => $part->components->pluck('name')->all()]);
    }

    public static function findPart(string $name): ?self
    {
        return static::query()->where('kind', self::PART)->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first();
    }

    /** Simpan bagian (bila baru) dan kembalikan modelnya. */
    public static function rememberPart(string $name): self
    {
        $name = trim($name);

        return static::findPart($name) ?? static::create([
            'kind' => self::PART,
            'name' => $name,
            'sort_order' => (int) static::query()->where('kind', self::PART)->max('sort_order') + 1,
        ]);
    }

    /** Simpan komponen ke bagiannya (bila baru) dan kembalikan nama bakunya. */
    public static function rememberComponent(string $part, string $name): string
    {
        $parent = static::rememberPart($part);
        $name = trim($name);
        $existing = $parent->components()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        return ($existing ?? $parent->components()->create([
            'kind' => self::COMPONENT,
            'name' => $name,
            'sort_order' => (int) $parent->components()->max('sort_order') + 1,
        ]))->name;
    }
}
