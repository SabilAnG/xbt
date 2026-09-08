<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionCost extends Model
{
    protected $fillable = [
        'production_id', 'cost_component_id', 'qty', 'minutes', 'rate', 'rate_type', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'minutes' => 'decimal:2',
            'rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            // Proses bertarif per jam dihitung dari menitnya; borongan dari
            // jumlahnya. Tipe tarif ikut dibekukan agar nota lama tetap
            // terbaca walau master biayanya nanti diubah.
            $row->subtotal = $row->rate_type === 'per_hour'
                ? (float) $row->rate * ((float) $row->minutes / 60) * max((float) $row->qty, 1)
                : (float) $row->rate * (float) $row->qty;
        });
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(CostComponent::class, 'cost_component_id');
    }
}
