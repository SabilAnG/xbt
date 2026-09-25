<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pengaturan situs publik.
 *
 * Nama toko, logo, kontak, dan nomor WhatsApp yang dipakai seluruh halaman
 * publik — dan juga oleh panel ini sendiri untuk merek dan favicon-nya.
 *
 * Kuncinya memakai titik (`site.name`), tapi nama isian HTML tidak boleh
 * memuat titik: PHP mengubahnya jadi garis bawah saat menguraikan form, dan
 * `site.name` akan tiba sebagai `site_name` tanpa ada yang memberi tahu. Jadi
 * pemetaannya ditulis tegas di sini, bukan diserahkan ke kebetulan.
 */
class PengaturanSitusController extends Controller
{
    /** Kelompok isian: judul => [kunci setting => [label, jenis]]. */
    private const MEDAN = [
        'Identitas' => [
            'site.name' => ['Nama Website', 'teks'],
            'site.tagline' => ['Tagline', 'teks'],
        ],
        'Kontak' => [
            'contact.email' => ['Email', 'email'],
            'contact.phone' => ['Telepon', 'teks'],
            'contact.address' => ['Alamat', 'teks'],
        ],
        'WhatsApp' => [
            'whatsapp.primary' => ['Nomor utama', 'teks'],
            'whatsapp.sales1' => ['Sales 1', 'teks'],
            'whatsapp.sales2' => ['Sales 2', 'teks'],
            'whatsapp.sales3' => ['Sales 3', 'teks'],
        ],
        'Media sosial' => [
            'social.instagram' => ['Instagram', 'url'],
            'social.facebook' => ['Facebook', 'url'],
            'social.tiktok' => ['TikTok', 'url'],
            'social.youtube' => ['YouTube', 'url'],
        ],
    ];

    /** Berkas gambar, ditangani terpisah karena diunggah, bukan diketik. */
    private const GAMBAR = [
        'site.logo' => 'Logo (latar gelap)',
        'site.logo_light' => 'Logo (latar terang)',
        'site.favicon' => 'Favicon',
    ];

    public function index(): View
    {
        $nilai = [];

        foreach (array_merge(...array_values(self::MEDAN)) as $kunci => $ignored) {
            $nilai[$kunci] = Setting::get($kunci);
        }

        foreach (array_keys(self::GAMBAR) as $kunci) {
            $nilai[$kunci] = Setting::get($kunci);
        }

        return view('panel.pengaturan.index', [
            'medan' => self::MEDAN,
            'gambar' => self::GAMBAR,
            'nilai' => $nilai,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $aturan = ['gambar' => ['array']];

        foreach (array_merge(...array_values(self::MEDAN)) as $kunci => [$label, $jenis]) {
            $aturan['isian.'.$this->aman($kunci)] = match ($jenis) {
                'email' => ['nullable', 'email', 'max:255'],
                'url' => ['nullable', 'url', 'max:2048'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        foreach (array_keys(self::GAMBAR) as $kunci) {
            $aturan['gambar.'.$this->aman($kunci)] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,ico,svg', 'max:2048'];
        }

        $request->validate($aturan);

        foreach (array_merge(...array_values(self::MEDAN)) as $kunci => $ignored) {
            Setting::put($kunci, $request->input('isian.'.$this->aman($kunci)));
        }

        $diunggah = 0;

        foreach (array_keys(self::GAMBAR) as $kunci) {
            $berkas = $request->file('gambar.'.$this->aman($kunci));

            if (! $berkas) {
                continue;
            }

            // Berkas lama TIDAK dibuang: logo bawaan yang dibawa dari situs
            // sebelumnya ada di assets/images/ dan masih dipakai halaman lain.
            // Yang menimpanya di sini cuma nilai settingnya.
            Setting::put($kunci, $berkas->storeAs(
                'storage/situs',
                Str::ulid().'.'.$berkas->extension(),
                'site',
            ));

            $diunggah++;
        }

        return back()->with(
            'sukses',
            'Pengaturan disimpan.'.($diunggah > 0 ? " {$diunggah} gambar diperbarui." : ''),
        );
    }

    /**
     * Kunci setting -> nama isian HTML yang aman.
     *
     * PHP mengubah titik jadi garis bawah saat menguraikan nama isian, jadi
     * `site.name` akan tiba sebagai `site_name` dan pasangannya hilang. Titik
     * ditukar sendiri di sini supaya perubahannya disengaja, bukan kejutan.
     */
    private function aman(string $kunci): string
    {
        return str_replace('.', '__', $kunci);
    }
}
