<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tampilan kaca panel admin.
 *
 * Dua hal di lapisan ini gagal tanpa pesan apa pun — halaman tetap terbuka,
 * tes lain tetap hijau, hanya tampilannya yang diam-diam kembali polos. Itu
 * sebabnya keduanya dikunci di sini.
 */
class TemaPanelTest extends TestCase
{
    /**
     * Filament 5 menyimpan --primary-500 dan kawan-kawan sebagai oklch() utuh,
     * bukan tiga angka kanal. Menulis rgb(var(--primary-500) / 0.4) menghasilkan
     * warna yang tidak sah: peramban membuang seluruh deklarasinya, dan panel
     * tampil tanpa cahaya sama sekali. Pengencerannya harus lewat color-mix.
     */
    public function test_warna_cahaya_tidak_memakai_bentuk_kanal_rgb(): void
    {
        $gaya = view('filament.tema-kaca')->render();

        $this->assertDoesNotMatchRegularExpression(
            '/rgb\(\s*var\(--(primary|gray|danger|success|warning)-\d+\)/',
            $gaya,
            'Warna Filament berbentuk oklch(); encerkan dengan color-mix, bukan rgb(var(--x) / a).'
        );

        $this->assertStringContainsString('color-mix(in srgb, var(--primary-500)', $gaya);
    }

    /**
     * Topbar Filament 5 adalah .fi-topbar itu sendiri. Versi sebelumnya
     * membungkusnya dalam <nav>, dan selektor .fi-topbar > nav yang tertinggal
     * tidak cocok dengan apa pun — topbar kehilangan kacanya tanpa keluhan.
     */
    public function test_kaca_menempel_pada_topbar_yang_benar(): void
    {
        $gaya = view('filament.tema-kaca')->render();

        $this->assertStringNotContainsString('.fi-topbar > nav', $gaya);
        $this->assertMatchesRegularExpression('/\.fi-topbar,\s*\n\s*\.fi-sidebar\s*\{/', $gaya);
    }

    /**
     * Tombol terang/gelap harus menulis ke kunci yang memang dibaca Filament
     * saat halaman dimuat. Kunci lain membuat pilihannya hilang tiap pindah
     * halaman — tombolnya tampak bekerja, tapi hanya sampai klik berikutnya.
     */
    public function test_tombol_tema_menulis_kunci_yang_dibaca_filament(): void
    {
        $tombol = view('filament.tombol-tema')->render();

        $this->assertStringContainsString("localStorage.setItem('theme'", $tombol);
        $this->assertStringContainsString("classList.toggle('dark')", $tombol);
        $this->assertStringContainsString('fi-tema-tombol', $tombol);
    }
}
