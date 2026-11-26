<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Jasa vendor: siapa (supplier), proses apa, dan tarif per satuan. */
class VendorService extends Model
{
    protected $fillable = ['supplier_id', 'process', 'rate', 'unit', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(FormulaComponent::class);
    }

    public function formulas(): HasMany
    {
        return $this->hasMany(Formula::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return $this->process.' · '.$this->supplier?->name.' — '.rupiah($this->rate).'/'.$this->unit;
    }

    /** Untuk pilihan di form formula. */
    public function toOption(): array
    {
        return [
            'id' => $this->id,
            'vendor' => $this->supplier?->name,
            'process' => $this->process,
            'rate' => (float) $this->rate,
            'unit' => $this->unit,
            'label' => $this->label(),
        ];
    }

    /**
     * Jasa untuk vendor + proses; dibuat bila belum ada (tarif hanya dipakai
     * saat membuat baru).
     */
    public static function remember(?string $vendor, ?string $process, float $rate = 0): ?self
    {
        $supplier = Supplier::resolve($vendor);

        if (! $supplier) {
            return null;
        }

        $process = trim((string) $process) ?: 'Jasa';
        $existing = static::query()->where('supplier_id', $supplier->id)->whereRaw('LOWER(process) = ?', [mb_strtolower($process)])->first();

        return $existing ?? static::create(['supplier_id' => $supplier->id, 'process' => $process, 'rate' => $rate]);
    }

    /** Salin vendor, proses, dan tarif terbaru ke komponen & formula yang memakainya. */
    public function syncUsages(): void
    {
        $values = ['vendor' => $this->supplier->name, 'process' => $this->process, 'service_cost' => $this->rate];
        $this->components()->update($values);
        $this->formulas()->update($values);
    }

    public function usageCount(): int
    {
        return $this->components()->count() + $this->formulas()->count();
    }
}
