<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Services\SesiPartner;
use App\Support\HakPartner;
use App\Support\Tutorial;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Hal-hal yang membuat sistem partner bisa benar-benar dipakai, bukan sekadar
 * jalan. Semuanya ditemukan lewat audit, dan tiap satu punya akibat nyata
 * kalau kambuh.
 */
class PartnerKelengkapanTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------ identitas toko partner

    /**
     * Tanpa ini seluruh halaman toko partner memakai nama, email, dan nomor
     * WhatsApp Hypersonic — dan pembeli yang menekan tombol WhatsApp di sana
     * menghubungi kami, bukan yang berjualan.
     */
    public function test_identitas_toko_diambil_dari_setting_bila_ada(): void
    {
        Setting::put('site.name', 'Knalpot Jaya');
        Setting::put('contact.email', 'jaya@contoh.test');
        Setting::put('whatsapp.primary', '0812-3456-7890');

        $this->assertSame('Knalpot Jaya', setting_nama());
        $this->assertSame('jaya@contoh.test', setting_email());

        // Hanya angka — bentuk yang dipakai wa.me maupun tel:
        $this->assertSame('081234567890', setting_wa());
    }

    /** Situs induk tidak boleh berubah kalau tabel settings-nya kosong. */
    public function test_tanpa_setting_identitas_tetap_milik_hypersonic(): void
    {
        $this->assertSame('Hypersonic Speed Tech', setting_nama());
        $this->assertSame('hypersonicspeedtech@gmail.com', setting_email());
        $this->assertSame('62895337161221', setting_wa());
    }

    // ------------------------------------------------------ keluar dari panel

    /**
     * Tanpa ini sesi yang sama masih menyimpan tanda partnernya sesudah logout,
     * dan orang berikutnya yang masuk lewat browser itu — termasuk admin
     * Hypersonic — sempat mendarat di database partner.
     */
    public function test_keluar_melupakan_partner_di_sesi(): void
    {
        session([SesiPartner::KUNCI => 'partner-lama']);

        Event::dispatch(new Logout('web', null));

        $this->assertNull(session(SesiPartner::KUNCI));
    }

    // --------------------------------------------------- pendaftaran dibatasi

    /**
     * Formulir terbuka untuk siapa saja. Tanpa batas, satu skrip bisa memenuhi
     * antrean persetujuan dengan ribuan baris dalam semenit.
     */
    public function test_pendaftaran_dibatasi_percobaannya(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'partner.register.store');

        $this->assertNotNull($route);
        $this->assertContains('throttle:5,60', $route->gatherMiddleware());
    }

    // ------------------------------------------------------ peringatan masa pakai

    #[DataProvider('masaPakai')]
    public function test_peringatan_muncul_hanya_di_minggu_terakhir(int $hari, bool $harusMuncul): void
    {
        $partner = new Tenant([
            'name' => 'Knalpot Jaya',
            'status' => Tenant::AKTIF,
            'expires_at' => now()->addDays($hari)->endOfDay(),
        ]);

        tenancy()->tenant = $partner;

        $html = view('filament.peringatan-masa-pakai')->render();
        $muncul = str_contains($html, '<div class="fi-masa-pakai');

        tenancy()->tenant = null;

        $this->assertSame($harusMuncul, $muncul, "Masa pakai $hari hari.");
    }

    /** @return array<string, array{int, bool}> */
    public static function masaPakai(): array
    {
        return [
            'habis hari ini' => [0, true],
            'dua hari lagi' => [2, true],
            'tujuh hari lagi' => [7, true],
            'delapan hari lagi' => [8, false],
            'sudah lewat' => [-1, false],
        ];
    }

    /** Tombol perpanjang menuju admin Hypersonic, bukan nomor partner sendiri. */
    public function test_tautan_perpanjang_menuju_admin_pusat(): void
    {
        config(['app.admin_whatsapp' => '+62 895-3371-61221']);

        $tautan = HakPartner::tautanPerpanjang();

        $this->assertStringContainsString('https://wa.me/62895337161221?', $tautan);
        $this->assertStringContainsString(rawurlencode('memperpanjang langganan'), $tautan);
    }

    // ------------------------------------------------------------- panduan

    /**
     * Menu tanpa panduan tidak memunculkan tombol bukunya sama sekali, jadi
     * yang terlewat hilang diam-diam.
     */
    public function test_setiap_menu_punya_panduan(): void
    {
        $panduan = array_keys(Tutorial::semua());

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $kunci = 'resources.'.Str::of(class_basename($resource))
                ->replace('Resource', '')->kebab()->plural()->toString().'.index';

            $this->assertContains($kunci, $panduan, "Menu [$kunci] belum punya panduan.");
        }
    }

    public function test_setiap_panduan_punya_tangkapan_layar(): void
    {
        foreach (array_keys(Tutorial::semua()) as $kunci) {
            $this->assertNotNull(
                Tutorial::gambar($kunci),
                "Panduan [$kunci] belum punya tangkapan layar di public/tutorial."
            );
        }
    }

    // -------------------------------------------------------- kabar ke partner

    /** Pesan WhatsApp menyesuaikan keadaan partner, bukan satu kalimat untuk semua. */
    public function test_pesan_whatsapp_mengikuti_keadaan_partner(): void
    {
        $partner = new Tenant([
            'name' => 'Knalpot Jaya',
            'owner_name' => 'Budi',
            'owner_email' => 'budi@contoh.test',
            'owner_phone' => '081234567890',
            'slug' => 'knalpot-jaya',
            'status' => Tenant::MENUNGGU,
        ]);

        $this->assertStringContainsString(rawurlencode('sedang diproses'), $partner->tautanWhatsapp());

        $partner->status = Tenant::AKTIF;
        $partner->expires_at = now()->addWeek();
        $this->assertStringContainsString(rawurlencode('sudah aktif'), $partner->tautanWhatsapp());

        $partner->expires_at = now()->subDay();
        $this->assertStringContainsString(rawurlencode('sudah habis'), $partner->tautanWhatsapp());

        // 08xx jadi 628xx — bentuk yang dipakai wa.me
        $this->assertStringContainsString('wa.me/6281234567890', $partner->tautanWhatsapp());
    }
}
