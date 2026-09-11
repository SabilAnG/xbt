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

/*
|--------------------------------------------------------------------------
| Identitas toko yang sedang dibuka
|--------------------------------------------------------------------------
| Nilai-nilai ini dulu tertulis langsung di view, sehingga setiap toko partner
| menampilkan nama, email, dan nomor WhatsApp Hypersonic. Bukan sekadar salah
| nama: pembeli yang menekan tombol WhatsApp di toko partner menghubungi kami,
| bukan yang berjualan.
|
| Nilai bawaannya tetap milik Hypersonic, supaya situs induk tidak berubah
| sedikit pun bila tabel settings-nya kosong.
*/

if (! function_exists('setting_nama')) {
    function setting_nama(): string
    {
        return (string) Setting::get('site.name', 'Hypersonic Speed Tech');
    }
}

if (! function_exists('setting_email')) {
    function setting_email(): string
    {
        return (string) Setting::get('contact.email', 'hypersonicspeedtech@gmail.com');
    }
}

if (! function_exists('setting_wa')) {
    /** Hanya angka — bentuk yang dipakai tautan wa.me maupun tel:. */
    function setting_wa(): string
    {
        $nomor = (string) Setting::get('whatsapp.primary', '62895337161221');

        return preg_replace('/\D/', '', $nomor) ?: '62895337161221';
    }
}
