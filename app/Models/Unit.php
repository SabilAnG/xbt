<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Satuan barang: Pcs, Lembar, Batang, Kg, cm², … */
class Unit extends Model
{
    protected $fillable = ['name', 'description', 'sort_order'];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** @return Collection<int, string> */
    public static function names(): Collection
    {
        return static::query()->ordered()->pluck('name');
    }

    /** Dipakai sebagai satuan beli atau satuan pakai oleh item mana pun. */
    public function isInUse(): bool
    {
        return Material::query()->where('unit', $this->name)->orWhere('use_unit', $this->name)->exists()
            || Item::query()->where('unit', $this->name)->exists();
    }
}
