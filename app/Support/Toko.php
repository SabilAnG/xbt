<?php

namespace App\Support;

/**
 * Alamat halaman toko, benar untuk situs induk maupun toko partner.
 *
 * Toko partner tinggal di bawah path `/toko/{slug}` — bukan subdomain — supaya
 * partner baru langsung hidup begitu disetujui, tanpa satu pun langkah di
 * server: tidak ada DNS yang perlu ditambah, tidak ada vhost, tidak ada
 * sertifikat.
 *
 * Halaman yang ditampilkan sama persis untuk keduanya; yang berbeda hanya
 * database di belakangnya dan awalan alamat ini.
 */
class Toko
{
    /**
     * Ubah alamat mutlak jadi alamat yang benar untuk toko yang sedang dibuka.
     *
     *   Toko::url('/products')
     *     di situs induk  -> /products
     *     di toko partner -> /toko/knalpot-jaya/products
     */
    public static function url(string $path = '/'): string
    {
        $slug = self::slug();

        if ($slug === null) {
            return $path;
        }

        $path = '/'.ltrim($path, '/');

        return rtrim('/toko/'.$slug.rtrim($path, '/'), '/') ?: '/toko/'.$slug;
    }

    /**
     * Slug toko yang sedang dibuka lewat path, kalau ada.
     *
     * Dibaca dari parameter route, bukan dari tenant yang aktif: panel admin
     * juga menyalakan tenant, dan di sana alamat halaman publik tidak boleh
     * ikut berawalan.
     */
    public static function slug(): ?string
    {
        $partner = request()->route('partner');

        return is_string($partner) && $partner !== '' ? $partner : null;
    }
}
