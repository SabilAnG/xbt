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
    /** Berkas tema yang dibangun Vite, bukan lagi view yang disuntik. */
    private static function tema(): string
    {
        return file_get_contents(resource_path('css/filament/admin/theme.css'));
    }

    /**
     * Tema tanpa komentar.
     *
     * Dulu penjelasan di berkas ini komentar Blade, dan hilang sendiri saat
     * view dirender. Sebagai berkas CSS ia ikut terbaca — dan isinya justru
     * mengutip bentuk-bentuk yang dilarang untuk menjelaskan larangannya, jadi
     * tes yang mencari bentuk terlarang akan menemukannya di prosa.
     */
    private static function temaTanpaKomentar(): string
    {
        return preg_replace('#/\*.*?\*/#s', '', self::tema());
    }

    /**
     * Panel produksi memuat temanya dari manifest Vite. Kalau image produksi
     * tidak pernah membangunnya, `public/build` tidak ada di server dan
     * Filament melempar ViteManifestNotFoundException — seluruh panel admin
     * mati dengan 500, bukan sekadar tampil polos. Gejalanya hanya muncul di
     * produksi, jadi dikunci di sini.
     */
    public function test_image_produksi_membangun_tema_panel(): void
    {
        $dockerfile = file_get_contents(base_path('docker/php/Dockerfile.prod'));

        $this->assertStringContainsString('AS assets', $dockerfile, 'Image produksi tidak punya stage pembangun aset.');
        $this->assertStringContainsString('npm run build', $dockerfile);

        preg_match_all('/COPY --from=assets.*public\/build/', $dockerfile, $salinan);

        $this->assertCount(
            2,
            $salinan[0],
            'Hasil build harus disalin ke runtime DAN ke nginx — nginx melayani berkas statisnya sendiri.'
        );
    }

    /**
     * Filament 5 menyimpan --primary-500 dan kawan-kawan sebagai oklch() utuh,
     * bukan tiga angka kanal. Menulis rgb(var(--primary-500) / 0.4) menghasilkan
     * warna yang tidak sah: peramban membuang seluruh deklarasinya, dan panel
     * tampil tanpa cahaya sama sekali. Pengencerannya harus lewat color-mix.
     */
    public function test_warna_cahaya_tidak_memakai_bentuk_kanal_rgb(): void
    {
        $gaya = self::temaTanpaKomentar();

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
        $gaya = self::tema();

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

    /**
     * Di bawah 64rem sidebar Filament bukan kolom, melainkan laci
     * `position: fixed; z-index: 30` yang mengambang di atas halaman.
     *
     * Tema ini diimpor sesudah gaya Filament dan berada di luar @layer, jadi
     * aturannya selalu menang atas gaya bawaan. Menetapkan `position` pada
     * .fi-sidebar mengembalikan laci itu ke aliran flex .fi-layout, di mana
     * lebarnya tetap terpakai walaupun laci sedang tertutup — isi halaman
     * terdorong ke luar layar. Di layar lebar tidak ada gejalanya sama sekali,
     * jadi kerusakan ini lolos dari mata siapa pun yang mengujinya di laptop.
     */
    public function test_laci_sidebar_tidak_dilepas_dari_posisi_mengambangnya(): void
    {
        $gaya = self::temaTanpaKomentar();

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $gaya, $aturan, PREG_SET_ORDER);

        foreach ($aturan as [, $selektor, $deklarasi]) {
            if (preg_match('/\.fi-sidebar(?![\w-])/', $selektor) !== 1) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/(?:^|[;\s])position\s*:/',
                $deklarasi,
                'Aturan `'.trim($selektor).'` menetapkan position pada sidebar; itu merusak laci di layar sempit.'
            );
        }
    }

    /**
     * Laci yang mengambang di atas isi halaman harus pekat, aturan yang sama
     * dengan modal dan menu. Kaca setipis 5,5% putih di mode gelap membuat
     * daftar menu dibaca di atas tabel yang masih terlihat di belakangnya.
     */
    public function test_laci_sidebar_pekat_di_lebar_ponsel(): void
    {
        $gaya = self::tema();

        $this->assertMatchesRegularExpression(
            '/@media\s*\(max-width:[^)]+\)\s*\{\s*\.fi-sidebar\s*\{[^}]*--kaca-bg-pekat/',
            $gaya,
            'Sidebar harus dibuat pekat di lebar laci, bukan kaca tipis.'
        );
    }
}
