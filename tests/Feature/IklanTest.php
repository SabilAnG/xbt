<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Banner iklan di situs.
 *
 * Yang dijaga di sini soal uang orang lain: iklan yang masa tayangnya habis
 * tidak boleh ikut tampil, dan iklan yang dibayar tidak boleh diam-diam hilang.
 * Kliknya dihitung di server, karena penghitung berbasis JavaScript di halaman
 * beriklan justru yang paling sering diblokir.
 */
class IklanTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanya_iklan_yang_sedang_tayang_yang_diambil(): void
    {
        $tayang = $this->iklan(['title' => 'Sedang tayang']);
        $this->iklan(['title' => 'Dimatikan', 'is_active' => false]);
        $this->iklan(['title' => 'Sudah lewat', 'ends_at' => now()->subDay()]);
        $this->iklan(['title' => 'Belum mulai', 'starts_at' => now()->addWeek()]);

        $hasil = Advertisement::untuk('footer');

        $this->assertCount(1, $hasil);
        $this->assertSame($tayang->id, $hasil->first()->id);
    }

    /** Batas tanggalnya inklusif — iklan yang habis hari ini masih tayang hari ini. */
    public function test_hari_terakhir_masih_terhitung_tayang(): void
    {
        $this->iklan(['starts_at' => now(), 'ends_at' => now()]);

        $this->assertCount(1, Advertisement::untuk('footer'));
    }

    public function test_iklan_diambil_sesuai_posisinya(): void
    {
        $this->iklan(['position' => 'footer']);
        $this->iklan(['position' => 'header']);

        $this->assertCount(1, Advertisement::untuk('footer'));
        $this->assertCount(1, Advertisement::untuk('header'));
        $this->assertCount(0, Advertisement::untuk('home_tengah'));
    }

    public function test_urutan_menentukan_siapa_lebih_dulu(): void
    {
        $this->iklan(['title' => 'Kedua', 'sort_order' => 20]);
        $this->iklan(['title' => 'Pertama', 'sort_order' => 10]);

        $this->assertSame('Pertama', Advertisement::untuk('footer')->first()->title);
    }

    public function test_klik_dihitung_lalu_diteruskan_ke_situs_pemasang(): void
    {
        $iklan = $this->iklan(['target_url' => 'https://instagram.com/tokonya']);

        $this->get(route('iklan.klik', $iklan))
            ->assertRedirect('https://instagram.com/tokonya');

        $this->assertSame(1, (int) $iklan->fresh()->clicks);

        $this->get(route('iklan.klik', $iklan));
        $this->assertSame(2, (int) $iklan->fresh()->clicks);
    }

    public function test_banner_muncul_di_halaman_dan_menunjuk_route_klik(): void
    {
        $iklan = $this->iklan(['title' => 'Banner Bengkel Jaya']);

        $this->get('/')
            ->assertSuccessful()
            ->assertSee('Banner Bengkel Jaya')
            ->assertSee(route('iklan.klik', $iklan));
    }

    public function test_iklan_yang_sudah_lewat_tidak_ikut_muncul_di_halaman(): void
    {
        $this->iklan(['title' => 'Banner Kedaluwarsa', 'ends_at' => now()->subDay()]);

        $this->get('/')->assertDontSee('Banner Kedaluwarsa');
    }

    public function test_halaman_pasang_iklan_memuat_posisi_dan_tombol_whatsapp(): void
    {
        config(['app.admin_whatsapp' => '+62 812-3456-7890']);

        $this->get('/pasang-iklan')
            ->assertSuccessful()
            ->assertSee('Pasang Iklan')
            ->assertSee('https://wa.me/6281234567890', false);
    }

    private function iklan(array $ganti = []): Advertisement
    {
        return Advertisement::create(array_merge([
            'title' => 'Iklan Uji',
            'position' => 'footer',
            'image_path' => 'iklan/contoh.jpg',
            'target_url' => 'https://contoh.test',
        ], $ganti));
    }
}
