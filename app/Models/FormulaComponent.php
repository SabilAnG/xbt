<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Komponen dalam satu bagian: Pureng, Plendes, P1, P2, Tabung, … */
class FormulaComponent extends Model
{
    protected $fillable = ['formula_part_id', 'name', 'qty', 'work_mode', 'vendor_service_id', 'vendor', 'process', 'service_cost', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'service_cost' => 'decimal:2'];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(FormulaPart::class, 'formula_part_id');
    }

    /** Komponen ini dijasakan ke vendor (mis. P1 dibending). */
    public function isVendorWork(): bool
    {
        return $this->work_mode === 'vendor' && filled($this->vendor);
    }

    /** Jasa vendor per knalpot = jumlah komponen × tarif per buah. */
    public function serviceCostPerUnit(): float
    {
        return $this->isVendorWork() ? (float) $this->qty * (float) $this->service_cost : 0.0;
    }

    public function vendorService(): BelongsTo
    {
        return $this->belongsTo(VendorService::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(FormulaComponentMaterial::class)->orderBy('sort_order')->orderBy('id');
    }
}
