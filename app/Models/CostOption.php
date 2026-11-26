<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Master ongkos formula (las, finishing, packing, …) dengan tarif bawaan per knalpot. */
class CostOption extends Model
{
    /** Cara bayar ongkos ke tukang. Keduanya biaya tenaga kerja yang dibayar lewat Penggajian. */
    public const PAYMENT_MODES = [
        'gaji' => 'Termasuk gaji harian',
        'borongan' => 'Borongan per produksi',
    ];

    protected $fillable = ['name', 'amount', 'payment_mode', 'sort_order'];

    public function isPiecework(): bool
    {
        return $this->payment_mode === 'borongan';
    }

    public function modeLabel(): string
    {
        return self::PAYMENT_MODES[$this->payment_mode] ?? $this->payment_mode;
    }

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
