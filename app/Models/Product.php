<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'fitment',
        'price',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    // NOTE: deliberately no getRouteKeyName() override. Setting it to 'slug'
    // globally also changes how Filament resolves records, which breaks the
    // admin panel's /admin/products/{id}/edit URLs. The public route binds the
    // slug explicitly instead: /products/{product:slug}.

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The image the listing card uses — the first gallery image's thumbnail.
     */
    public function cardImage(): ?ProductImage
    {
        return $this->images->first();
    }
}
