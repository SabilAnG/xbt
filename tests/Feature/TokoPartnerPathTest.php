<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\SesiPartner;
use App\Support\Toko;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Toko partner hidup di path, bukan subdomain.
 *
 * Itu yang membuat partner yang disetujui pukul sepuluh sudah bisa membuka
 * tokonya pukul sepuluh lewat: tidak ada DNS yang perlu ditambah, tidak ada
 * vhost, tidak ada sertifikat — tidak satu pun langkah di server.
 */
class TokoPartnerPathTest extends TestCase
{
    use RefreshDatabase;

    /** Di situs induk alamatnya apa adanya. */
    public function test_tanpa_partner_alamat_tidak_berubah(): void
    {
        $this->assertSame('/products', Toko::url('/products'));
        $this->assertSame('/', Toko::url('/'));
        $this->assertNull(Toko::slug());
    }

    public function test_di_toko_partner_alamat_diberi_awalan(): void
    {
        // Route palsu yang membawa parameter partner, seperti route toko asli.
        Route::get('/toko/{partner}/uji', fn () => response()->json([
            'beranda' => Toko::url('/'),
            'produk' => Toko::url('/products'),
            'satu' => Toko::url('/products/knalpot-mio'),
            'slug' => Toko::slug(),
        ]))->middleware('web');

        $this->get('/toko/knalpot-jaya/uji')->assertJson([
            'beranda' => '/toko/knalpot-jaya',
            'produk' => '/toko/knalpot-jaya/products',
            'satu' => '/toko/knalpot-jaya/products/knalpot-mio',
            'slug' => 'knalpot-jaya',
        ]);
    }

    public function test_route_toko_terdaftar_untuk_tiap_halaman_publik(): void
    {
        $wajib = [
            'toko.home', 'toko.about', 'toko.contact', 'toko.workshop',
            'toko.tracking', 'toko.products.index', 'toko.products.show',
        ];

        foreach ($wajib as $nama) {
            $this->assertNotNull(
                Route::getRoutes()->getByName($nama),
                "Route [$nama] belum terdaftar untuk toko partner."
            );
        }
    }

    /**
     * Mendaftar jadi partner dan memasang iklan adalah urusan situs induk.
     * Kalau ikut hidup di toko partner, pendaftarannya mendarat di database
     * partner itu — tempat yang tidak pernah dibaca siapa pun.
     */
    public function test_halaman_milik_pusat_tidak_punya_kembaran_di_toko(): void
    {
        foreach (['toko.partner.register', 'toko.pasang-iklan', 'toko.iklan.klik'] as $nama) {
            $this->assertNull(
                Route::getRoutes()->getByName($nama),
                "Route [$nama] seharusnya tidak ada di toko partner."
            );
        }
    }

    /** Alamat yang ditampilkan ke admin memakai bentuk path. */
    public function test_alamat_partner_berbentuk_path(): void
    {
        config(['app.url' => 'https://shop.garagehs-speed.com']);

        $partner = new Tenant(['slug' => 'knalpot-jaya']);

        $this->assertSame('https://shop.garagehs-speed.com/toko/knalpot-jaya', $partner->alamat());
        $this->assertSame('shop.garagehs-speed.com/toko/knalpot-jaya', $partner->alamatRingkas());
    }

    // -------------------------------------------------------------- sesi

    public function test_sesi_mengingat_dan_melupakan_partner(): void
    {
        $partner = new Tenant(['id' => 'abc', 'name' => 'Knalpot Jaya']);

        SesiPartner::ingat($partner);
        $this->assertSame('abc', session(SesiPartner::KUNCI));

        SesiPartner::lupakan();
        $this->assertNull(session(SesiPartner::KUNCI));
    }

    /** Admin pusat masuk lewat pintu yang sama, dan sesinya tidak menandai apa pun. */
    public function test_login_tanpa_partner_membersihkan_tanda_di_sesi(): void
    {
        session([SesiPartner::KUNCI => 'sisa-lama']);

        SesiPartner::ingat(null);

        $this->assertNull(session(SesiPartner::KUNCI));
    }

    /**
     * Barisnya dibaca ulang tiap permintaan, bukan disimpan di sesi: hak akses
     * dan masa pakai bisa berubah kapan saja di panel admin.
     */
    public function test_partner_yang_sudah_dihapus_membersihkan_sesinya_sendiri(): void
    {
        session([SesiPartner::KUNCI => 'sudah-tidak-ada']);

        $this->assertNull(SesiPartner::partner());
        $this->assertNull(session(SesiPartner::KUNCI));
    }
}
