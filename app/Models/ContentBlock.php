<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ContentBlock extends Model
{
    protected $fillable = ['key', 'page', 'label', 'type', 'value', 'sort_order'];

    private const CACHE_KEY = 'content_blocks.all';

    public const PAGES = [
        'global' => 'Global (header & footer)',
        'home' => 'Home',
        'products' => 'Products',
        'workshop' => 'Workshop',
        'about' => 'About',
        'contact' => 'Contact',
        'tracking' => 'Tracking',
        'privacy-policy' => 'Privacy Policy',
        'terms-and-conditions' => 'Terms & Conditions',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * @return array<string, string|null>
     */
    public static function all_values(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->pluck('value', 'key')->all()
        );
    }

    /**
     * Returns the stored copy for $key, falling back to the text the page
     * shipped with. The fallback is what keeps a page rendering correctly
     * before anyone has touched it in the admin.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::all_values()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }
}
