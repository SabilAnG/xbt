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
        'type',
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
     * through the admin panel (which stores a single file rather than conversions).
     */
    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    /** MIME untuk tag <source> video. */
    public function videoMime(): string
    {
        return match (strtolower(pathinfo($this->path, PATHINFO_EXTENSION))) {
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => 'video/mp4',
        };
    }

    public function thumb(): string
    {
        return $this->thumb_path ?: $this->path;
    }
}
