<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Komponen biaya produksi: jasa las, bubut, poles.
 */
class CostComponent extends Model
{
    public const TYPES = [
        'jasa' => 'Jasa',
        'tenaga_kerja' => 'Tenaga Kerja',
        'overhead' => 'Overhead',
    ];

    public const RATE_TYPES = [
        'per_hour' => 'Per jam (isi menit di formula)',
        'per_unit' => 'Per unit (tarif borongan)',
    ];

    protected $fillable = [
        'name', 'slug', 'type', 'rate_type', 'unit', 'rate', 'default_minutes',
        'description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'default_minutes' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function isHourly(): bool
    {
        return $this->rate_type === 'per_hour';
    }

    /**
     * Biaya untuk sejumlah menit kerja. Untuk komponen borongan, menit
     * diabaikan dan tarifnya dipakai apa adanya.
     */
    public function costForMinutes(float $minutes, float $qty = 1): float
    {
        return $this->isHourly()
            ? (float) $this->rate * ($minutes / 60) * $qty
            : (float) $this->rate * $qty;
    }

    public function displayRate(): string
    {
        return 'Rp '.number_format((float) $this->rate, 0, ',', '.')
            .'/'.($this->isHourly() ? 'jam' : ($this->unit ?: 'unit'));
    }

    public function formulaLines(): HasMany
    {
        return $this->hasMany(FormulaCost::class);
    }
}
