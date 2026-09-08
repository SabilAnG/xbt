<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rak penyimpanan di dalam gudang.
 */
class Rack extends Model
{
    protected $fillable = ['warehouse_id', 'name', 'code', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MaterialStock::class);
    }

    /** "Gudang Komponen / A1" — dipakai di dropdown agar tidak ambigu. */
    public function fullName(): string
    {
        return trim(($this->warehouse?->name ?? '').' / '.$this->code);
    }
}
