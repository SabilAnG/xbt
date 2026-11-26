<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Rencana produksi: beberapa formula dengan jumlah targetnya. Rencana tidak
 * menyentuh stok; ia menghitung kebutuhan bahan dan kekurangannya (lihat
 * ProductionPlanner), lalu menjadi dasar pembelian dan nota produksi.
 */
class ProductionPlan extends Model
{
    public const STATUSES = [
        'draft' => 'Draf',
        'proses' => 'Diproses',
        'selesai' => 'Selesai',
        'batal' => 'Batal',
    ];

    protected $fillable = ['plan_number', 'planned_for', 'title', 'status', 'notes'];

    protected function casts(): array
    {
        return ['planned_for' => 'date'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionPlanLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function productions(): HasManyThrough
    {
        return $this->hasManyThrough(Production::class, ProductionPlanLine::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(MaterialPurchase::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function totalUnits(): float
    {
        return (float) $this->lines->sum('qty');
    }

    /** Selesai bila semua baris sudah punya produksi yang dibukukan. */
    public function refreshStatus(): void
    {
        if ($this->status === 'batal') {
            return;
        }

        $this->loadMissing('lines.productions');
        $lines = $this->lines;
        $started = $lines->contains(fn (ProductionPlanLine $line) => $line->productions->isNotEmpty());
        $done = $lines->isNotEmpty() && $lines->every(fn (ProductionPlanLine $line) => $line->productions->contains('status', Production::POSTED));

        $this->forceFill(['status' => $done ? 'selesai' : ($started ? 'proses' : 'draft')])->save();
    }
}
