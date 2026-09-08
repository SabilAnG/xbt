<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'path',
        'thumb_path',
        'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Thumbnail variant, falling back to the full image for anything uploaded
     * through Filament (which stores a single file rather than conversions).
     */
    public function thumb(): string
    {
        return $this->thumb_path ?: $this->path;
    }
}
