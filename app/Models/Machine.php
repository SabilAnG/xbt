<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mesin produksi. Biaya per jam diturunkan dari tiga hal yang memang Anda tahu:
 * harga mesin, umur ekonomisnya, dan berapa jam dipakai setahun.
 *
 * Listrik dihitung di sini — bukan sebagai pos terpisah — supaya tidak
 * terhitung dua kali bersama overhead.
 */
class Machine extends Model
{
    /** Dipakai bila tarif listrik belum diatur di menu pengaturan. */
    public const DEFAULT_TARIF_LISTRIK = 1500.0;

    protected $fillable = [
        'name', 'code', 'purchase_price', 'economic_life_years', 'hours_per_year',
        'power_kw', 'maintenance_per_year', 'description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'hours_per_year' => 'decimal:2',
            'power_kw' => 'decimal:3',
            'maintenance_per_year' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function formulaLines(): HasMany
    {
        return $this->hasMany(FormulaMachine::class);
    }

    // ------------------------------------------------------- biaya per jam

    public static function tarifListrik(): float
    {
        return (float) Setting::get('produksi.tarif_listrik', self::DEFAULT_TARIF_LISTRIK);
    }

    /** Total jam pakai sepanjang umur ekonomis. */
    private function lifetimeHours(): float
    {
        $jam = (int) $this->economic_life_years * (float) $this->hours_per_year;

        return $jam > 0 ? $jam : 1.0;
    }

    /** Penyusutan per jam — harga mesin dibagi total jam pakainya. */
    public function depreciationPerHour(): float
    {
        return (float) $this->purchase_price / $this->lifetimeHours();
    }

    /** Listrik per jam — daya x tarif. */
    public function electricityPerHour(): float
    {
        return (float) $this->power_kw * self::tarifListrik();
    }

    public function maintenancePerHour(): float
    {
        $jam = (float) $this->hours_per_year;

        return $jam > 0 ? (float) $this->maintenance_per_year / $jam : 0.0;
    }

    public function hourlyCost(): float
    {
        return $this->depreciationPerHour()
            + $this->electricityPerHour()
            + $this->maintenancePerHour();
    }

    public function costForMinutes(float $minutes): float
    {
        return $this->hourlyCost() * ($minutes / 60);
    }

    /**
     * @return array<string, float>
     */
    public function hourlyBreakdown(): array
    {
        return [
            'penyusutan' => $this->depreciationPerHour(),
            'listrik' => $this->electricityPerHour(),
            'maintenance' => $this->maintenancePerHour(),
            'total' => $this->hourlyCost(),
        ];
    }

    public function displayHourlyCost(): string
    {
        return 'Rp '.number_format($this->hourlyCost(), 0, ',', '.').'/jam';
    }
}
