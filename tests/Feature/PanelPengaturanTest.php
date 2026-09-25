<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengaturan situs di panel baru.
 *
 * Yang dikunci di sini terutama soal UNGGAHAN, karena dua hal di lapisan itu
 * gagal tanpa pesan yang berguna: SVG yang lolos jadi lubang XSS tersimpan, dan
 * favicon .ico yang ditolak diam-diam padahal itu format favicon yang dipakai
 * situs ini.
 */
class PanelPengaturanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('site');
        $this->actingAs(User::factory()->create());
    }

    /**
     * SVG boleh memuat <script>, dan berkas yang diunggah disimpan di public/
     * lalu disajikan dari origin yang sama dengan aplikasi. Logo memang
     * dirender lewat <img> yang tidak menjalankan skrip, tapi berkasnya tetap
     * bisa dibuka langsung lewat URL-nya — satu tautan ke admin lain sudah
     * cukup untuk mencuri sesinya.
     *
     * Apa yang SEBENARNYA dijaga tes ini: aturan `image` bawaan Laravel 12
     * sudah menolak SVG sendiri (butuh `image:allow_svg` untuk mengizinkannya),
     * jadi selama aturannya memakai `image`, svg di daftar mimes tidak berbahaya
     * — hanya menyesatkan. Bahayanya muncul kalau `image` ditukar `file`, dan
     * itu BUKAN hal yang mengada-ada: aturan favicon di bawah memang harus
     * memakai `file`, karena `image` menolak .ico.
     *
     * Tes ini terbukti merah pada bentuk yang berbahaya itu.
     */
    public function test_svg_ditolak_sebagai_logo(): void
    {
        // Isinya harus BENAR-BENAR SVG. `fake()->create()` membuat berkas
        // kosong yang tidak dikenali sebagai svg, jadi tesnya akan hijau bahkan
        // ketika svg diizinkan — hijau karena alasan yang salah, dan itu lebih
        // buruk daripada tidak ada tesnya.
        $svg = <<<'XML'
        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">
            <script>alert(document.cookie)</script>
        </svg>
        XML;

        $this->put(route('panel.pengaturan.update'), [
            'gambar' => ['site__logo' => UploadedFile::fake()->createWithContent('jahat.svg', $svg)],
        ])->assertSessionHasErrors('gambar.site__logo');

        $this->assertNull(Setting::get('site.logo'));
        $this->assertCount(0, Storage::disk('site')->allFiles());
    }

    /**
     * Aturan `image` bawaan Laravel tidak menerima .ico — hanya jpg, jpeg, png,
     * bmp, gif, svg, webp. Memakainya untuk favicon membuat favicon situs ini,
     * yang memang favicon.ico, selalu ditolak tanpa sebab yang kelihatan.
     */
    public function test_favicon_ico_diterima(): void
    {
        $this->put(route('panel.pengaturan.update'), [
            'gambar' => ['site__favicon' => UploadedFile::fake()->create('favicon.ico', 4, 'image/x-icon')],
        ])->assertSessionHasNoErrors();

        $this->assertStringEndsWith('.ico', (string) Setting::get('site.favicon'));
    }

    public function test_isian_teks_tersimpan_ke_kunci_bertitik(): void
    {
        $this->put(route('panel.pengaturan.update'), [
            'isian' => [
                'site__name' => 'Toko Uji',
                'contact__email' => 'halo@contoh.test',
                'whatsapp__sales1' => '628123',
            ],
        ])->assertSessionHasNoErrors();

        // Nama isian HTML memakai garis bawah ganda karena PHP mengubah titik
        // jadi garis bawah saat menguraikan form; yang TERSIMPAN tetap
        // kunci bertitik.
        $this->assertSame('Toko Uji', Setting::get('site.name'));
        $this->assertSame('halo@contoh.test', Setting::get('contact.email'));
        $this->assertSame('628123', Setting::get('whatsapp.sales1'));
    }

    public function test_email_tidak_sah_ditolak(): void
    {
        $this->put(route('panel.pengaturan.update'), [
            'isian' => ['contact__email' => 'bukan-email'],
        ])->assertSessionHasErrors('isian.contact__email');
    }

    public function test_tamu_tidak_bisa_membuka_pengaturan(): void
    {
        auth()->logout();

        $this->get(route('panel.pengaturan.index'))
            ->assertRedirect(route('panel.masuk'));
    }
}
