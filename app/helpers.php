<?php

use App\Models\ContentBlock;
use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Read a site setting, falling back to the value the site shipped with.
     * Both settings and content blocks are cached, so this is cheap to call
     * repeatedly from a Blade template.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('content')) {
    /**
     * Editable copy. $default is the wording the page originally had, so a page
     * still renders correctly before anything is changed in the admin.
     */
    function content(string $key, ?string $default = null): ?string
    {
        return ContentBlock::get($key, $default);
    }
}

if (! function_exists('content_image')) {
    /**
     * Editable image. Values are paths relative to public/, matching what
     * asset() expects — e.g. "assets/images/header1.png".
     */
    function content_image(string $key, ?string $default = null): string
    {
        $path = ContentBlock::get($key, $default);

        return $path ? asset($path) : '';
    }
}

if (! function_exists('stock_status')) {
    /**
     * Status stok versi buku kerja knalpot: HABIS bila tidak bersisa, MENIPIS
     * bila sudah menyentuh batas minimum, selain itu AMAN.
     */
    function stock_status(float $stock, float $minimum): string
    {
        if ($stock <= 0) {
            return 'HABIS';
        }

        return $stock <= $minimum ? 'MENIPIS' : 'AMAN';
    }
}

if (! function_exists('rupiah')) {
    /**
     * Format angka sebagai rupiah tanpa desimal: 1250000 → "Rp 1.250.000".
     */
    function rupiah(float|int|string|null $amount): string
    {
        $amount = (float) $amount;
        $formatted = number_format(abs($amount), 0, ',', '.');

        return ($amount < 0 ? '-Rp ' : 'Rp ').$formatted;
    }
}

if (! function_exists('qty')) {
    /**
     * Format jumlah: buang nol di belakang koma. 2.500 → "2,5"; 4.000 → "4".
     */
    function qty(float|int|string|null $value, int $decimals = 3): string
    {
        $formatted = number_format((float) $value, $decimals, ',', '.');

        return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }
}
