<?php

namespace Tests\Feature;

use App\Http\Middleware\PastikanPartnerAktif;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Toko partner yang masa pakainya habis.
 *
 * Yang dijaga di sini: pintunya tertutup, datanya tidak disentuh, dan tombol
 * langganannya membawa pesan yang sudah menyebut toko mana — supaya partner
 * tidak perlu menjelaskan ulang siapa dirinya, dan admin tidak perlu menebak.
 */
class PartnerKedaluwarsaTest extends TestCase
{
    use RefreshDatabase;

    private function lewatkan(Tenant $partner): Response
    {
        // Middleware dipanggil langsung: menyalakan tenancy sungguhan berarti
        // membuat database, dan yang diuji di sini bukan itu.
        app()->instance('tenant.uji', $partner);

        $middleware = new class($partner) extends PastikanPartnerAktif
        {
            public function __construct(private Tenant $partner) {}

            protected function partnerSekarang(): ?Tenant
            {
                return $this->partner;
            }
        };

        return $middleware->handle(Request::create('/'), fn () => new Response('toko jalan'));
    }

    public function test_partner_aktif_dibiarkan_lewat(): void
    {
        $balasan = $this->lewatkan(new Tenant([
            'name' => 'Knalpot Jaya',
            'status' => Tenant::AKTIF,
            'expires_at' => now()->addWeek(),
        ]));

        $this->assertSame(200, $balasan->getStatusCode());
        $this->assertSame('toko jalan', $balasan->getContent());
    }

    public function test_masa_habis_menampilkan_halaman_hitam_dan_tombol_langganan(): void
    {
        $balasan = $this->lewatkan(new Tenant([
            'name' => 'Knalpot Jaya',
            'status' => Tenant::AKTIF,
            'expires_at' => now()->subDay(),
        ]));

        // 402 Payment Required: jujur soal sebabnya, dan mesin pencari tidak
        // memperlakukannya sebagai halaman yang hilang selamanya.
        $this->assertSame(402, $balasan->getStatusCode());

        $isi = $balasan->getContent();

        $this->assertStringContainsString('BAYAR DULU BARU PAKE SAYA', $isi);
        $this->assertStringContainsString('Langganan', $isi);
        $this->assertStringContainsString('https://wa.me/', $isi);

        // Pesannya menyebut tokonya, sudah terisi.
        $this->assertStringContainsString(rawurlencode('melanjutkan langganan website saya'), $isi);
        $this->assertStringContainsString(rawurlencode('Knalpot Jaya'), $isi);
    }

    public function test_partner_yang_belum_disetujui_juga_ditutup(): void
    {
        $balasan = $this->lewatkan(new Tenant([
            'name' => 'Belum Disetujui',
            'status' => Tenant::MENUNGGU,
        ]));

        $this->assertSame(402, $balasan->getStatusCode());
    }

    /** Nomor WhatsApp boleh ditulis bebas; yang bukan angka dibuang. */
    public function test_nomor_whatsapp_dibersihkan_dari_tanda_baca(): void
    {
        config(['app.admin_whatsapp' => '+62 812-3456-7890']);

        $isi = $this->lewatkan(new Tenant([
            'name' => 'Knalpot Jaya',
            'status' => Tenant::AKTIF,
            'expires_at' => now()->subDay(),
        ]))->getContent();

        $this->assertStringContainsString('https://wa.me/6281234567890?', $isi);
    }
}
