<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Menu apa saja yang boleh dibuka seorang partner.
 *
 * Satu-satunya tempat aturan ini ditulis. Dipanggil dari `canAccess()`, yang di
 * Filament mengatur dua hal sekaligus: menu muncul atau tidak di navigasi, dan
 * halamannya bisa dibuka atau ditolak 403. Menyembunyikan dari navigasi saja
 * bukan penjagaan — alamatnya masih bisa diketik langsung.
 */
class HakPartner
{
    /**
     * Grup navigasi dipetakan ke hak yang dicentang admin saat menyetujui.
     *
     * Grup dipakai sebagai kunci, bukan nama kelas satu per satu: menu baru
     * yang lahir di grup yang sudah ada langsung ikut aturannya, tanpa perlu
     * ada yang ingat mendaftarkannya di sini.
     */
    public const GRUP_FITUR = [
        'Operasional' => 'operasional',
        'Produksi' => 'produksi',
        'Aset' => 'aset',
        'Master Data' => 'master',
        'Toko Online' => 'toko',
        'Website' => 'landing',
    ];

    /**
     * Menu yang tidak pernah ada di panel partner, apa pun haknya.
     *
     * Daftar partner dan iklan situs adalah urusan Hypersonic sendiri. Partner
     * tidak punya partner, dan tidak menjual ruang iklan di situs kami.
     */
    public const HANYA_PUSAT = [
        'App\Filament\Resources\Partners\PartnerResource',
        'App\Filament\Resources\Advertisements\AdvertisementResource',
    ];

    /**
     * Boleh dibuka atau tidak.
     *
     * Di panel pusat selalu boleh — aturan ini hanya berlaku bagi partner.
     */
    public static function boleh(string $kelas, ?string $grup): bool
    {
        $partner = self::partner();

        if (! $partner instanceof Tenant) {
            return true;
        }

        if (in_array($kelas, self::HANYA_PUSAT, true)) {
            return false;
        }

        $fitur = self::GRUP_FITUR[$grup] ?? null;

        // Grup yang tidak dikenal ditolak, bukan dibiarkan. Menu baru yang
        // lupa didaftarkan lebih baik hilang dari panel partner daripada
        // diam-diam terbuka untuk semua orang.
        return $fitur !== null && $partner->punyaHak($fitur);
    }

    /**
     * Tujuan tombol Perpanjang di panel partner: WhatsApp admin Hypersonic.
     *
     * Nomornya dari config, bukan dari Site settings — saat panel partner yang
     * terbuka, Site settings berisi nomor PARTNER, dan tombol ini justru harus
     * menghubungi kami.
     */
    public static function tautanPerpanjang(): string
    {
        $nomor = preg_replace('/\D/', '', (string) config('app.admin_whatsapp'));
        $partner = self::partner();

        $pesan = 'Halo, saya ingin memperpanjang langganan website saya'
            .($partner ? ' ('.$partner->name.')' : '').'.';

        return 'https://wa.me/'.$nomor.'?text='.rawurlencode($pesan);
    }

    /** Partner yang sedang dilayani, atau null bila ini panel pusat. */
    public static function partner(): ?Tenant
    {
        if (! function_exists('tenant')) {
            return null;
        }

        $partner = tenant();

        return $partner instanceof Tenant ? $partner : null;
    }
}
