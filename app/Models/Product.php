<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'item_id',
        'name',
        'slug',
        'fitment',
        'price',
        'currency',
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

    /** Knalpot di Stok Produk yang dijual lewat halaman ini (opsional). */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Harga untuk ditampilkan di website: "Rp 890.000" atau "$910.00". */
    public function formattedPrice(): string
    {
        return $this->currency === 'IDR'
            ? rupiah($this->price)
            : '$'.number_format((float) $this->price, 2);
    }

    /**
     * Data untuk tombol "+ Keranjang" (keranjang disimpan di browser).
     *
     * @return array{slug: string, name: string, price: float, currency: string, image: ?string, url: string}
     */
    public function cartPayload(): array
    {
        $image = $this->cardImage();

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'price' => (float) $this->price,
            'currency' => $this->currency ?: 'USD',
            'image' => $image ? asset($image->thumb()) : null,
            'url' => route('products.show', $this->slug, false),
        ];
    }

    /** Stok knalpot yang masih tersedia, bila produk ini tertaut ke stok. */
    public function availableStock(): ?float
    {
        return $this->item ? max((float) $this->item->stock, 0) : null;
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    // NOTE: deliberately no getRouteKeyName() override. The admin panel edits
    // products by id (/admin/website/produk/{id}/edit); the public route binds
    // the slug explicitly instead: /products/{product:slug}.

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The image the listing card uses — the first gallery photo (videos are skipped).
     */
    public function cardImage(): ?ProductImage
    {
        return $this->images->first(fn (ProductImage $media) => ! $media->isVideo());
    }
}
