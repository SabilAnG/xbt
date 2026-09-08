<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialOpnameItem extends Model
{
    protected $fillable = [
        'material_opname_id', 'material_id', 'rack_id',
        'system_qty', 'physical_qty', 'difference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:3',
            'physical_qty' => 'decimal:3',
            'difference' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        // Selisih selalu diturunkan agar tidak bisa bertentangan dengan angkanya.
        static::saving(function (self $row) {
            $row->difference = (float) $row->physical_qty - (float) $row->system_qty;
        });
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(MaterialOpname::class, 'material_opname_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }
}
