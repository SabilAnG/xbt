<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Type motor, mis. "Vario 160" milik brand Honda.
 */
class MotorcycleModel extends Model
{
    protected $fillable = [
        'motorcycle_brand_id', 'name', 'slug', 'year_from', 'year_to', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(MotorcycleBrand::class, 'motorcycle_brand_id');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class);
    }

    public function fullName(): string
    {
        return trim(($this->brand?->name ?? '').' '.$this->name);
    }
}
