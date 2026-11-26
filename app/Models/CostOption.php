<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Master ongkos formula (las, finishing, packing, …) dengan tarif bawaan per knalpot. */
class CostOption extends Model
{
    protected $fillable = ['name', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public static function findByName(string $name): ?self
    {
        return static::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first();
    }

    /** Simpan ongkos baru ke master (tarifnya jadi tarif bawaan); yang sudah ada dibiarkan. */
    public static function remember(string $name, float $amount = 0): self
    {
        return static::findByName($name) ?? static::create([
            'name' => trim($name),
            'amount' => $amount,
            'sort_order' => (int) static::query()->max('sort_order') + 1,
        ]);
    }

    public function usageCount(): int
    {
        return FormulaCost::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($this->name)])->distinct('formula_id')->count('formula_id');
    }
}
