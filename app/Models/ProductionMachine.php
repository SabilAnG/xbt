<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pemakaian mesin pada satu nota produksi.
 *
 * Tarif dibekukan saat pembukuan supaya penggantian mesin atau kenaikan tarif
 * listrik tidak mengubah HPP nota lama.
 */
class ProductionMachine extends Model
{
    protected $fillable = ['production_id', 'machine_id', 'minutes', 'rate', 'subtotal'];

    protected function casts(): array
    {
        return [
            'minutes' => 'decimal:2',
            'rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            $row->subtotal = (float) $row->rate * ((float) $row->minutes / 60);
        });
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
