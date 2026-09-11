<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pendaftaran partner dari halaman publik.
 *
 * Yang paling penting dijaga di sini bukan tampilannya, melainkan apa yang
 * TIDAK terjadi saat orang menekan kirim: tidak ada database baru, tidak ada
 * akun, tidak ada subdomain. Formulir yang terbuka untuk siapa saja tidak boleh
 * bisa menyuruh server membuat database.
 */
class PartnerPendaftaranTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_pendaftaran_bisa_dibuka(): void
    {
        $this->get('/jadi-partner')
            ->assertSuccessful()
            ->assertSee('Jadi Partner')
            ->assertSee('Akun Panel Admin');
    }

    public function test_pendaftaran_menyimpan_baris_menunggu_tanpa_membuat_apa_pun(): void
    {
        $this->post('/jadi-partner', $this->isian())
            ->assertRedirect('/jadi-partner')
            ->assertSessionHas('partner_terkirim', 'knalpot-jaya');

        $partner = Tenant::where('slug', 'knalpot-jaya')->sole();

        $this->assertSame(Tenant::MENUNGGU, $partner->status);
        $this->assertNull($partner->expires_at);
        $this->assertNull($partner->features);

        // Belum disetujui: belum punya subdomain, belum punya toko.
        $this->assertSame(0, $partner->domains()->count());
    }

    /** Sandi tidak pernah menginap sebagai teks, bahkan saat masih menunggu. */
    public function test_sandi_disimpan_sudah_ter_hash(): void
    {
        $this->post('/jadi-partner', $this->isian());

        $partner = Tenant::where('slug', 'knalpot-jaya')->sole();

        $this->assertNotSame('rahasia123', $partner->owner_password);
        $this->assertTrue(Hash::check('rahasia123', $partner->owner_password));
    }

    /**
     * Alamat toko jadi subdomain, jadi aturannya aturan DNS — bukan selera
     * kami. Spasi dan huruf besar tidak bisa jadi alamat.
     */
    public function test_alamat_toko_harus_layak_jadi_subdomain(): void
    {
        foreach (['Knalpot Jaya', 'knalpot_jaya', '-knalpot', 'knalpot-'] as $buruk) {
            $this->post('/jadi-partner', $this->isian(['slug' => $buruk]))
                ->assertSessionHasErrors('slug');
        }

        $this->assertSame(0, Tenant::count());
    }

    public function test_alamat_dan_email_tidak_boleh_kembar(): void
    {
        $this->post('/jadi-partner', $this->isian());

        $this->post('/jadi-partner', $this->isian(['owner_email' => 'lain@contoh.test']))
            ->assertSessionHasErrors('slug');

        $this->post('/jadi-partner', $this->isian(['slug' => 'toko-lain']))
            ->assertSessionHasErrors('owner_email');

        $this->assertSame(1, Tenant::count());
    }

    public function test_ulangan_sandi_harus_sama(): void
    {
        $this->post('/jadi-partner', $this->isian(['password_confirmation' => 'bedasendiri']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, Tenant::count());
    }

    // -------------------------------------------------------------- keadaan

    public function test_masa_pakai_habis_membuat_partner_tidak_aktif(): void
    {
        $partner = new Tenant([
            'status' => Tenant::AKTIF,
            'expires_at' => now()->subDay(),
            'features' => ['landing'],
        ]);

        $this->assertTrue($partner->kedaluwarsa());
        $this->assertFalse($partner->isAktif());
        $this->assertSame('Kedaluwarsa', $partner->displayStatus());

        // Haknya tetap tercatat — memperpanjang mengembalikan semuanya.
        $this->assertTrue($partner->punyaHak('landing'));
    }

    public function test_tanpa_batas_waktu_tidak_pernah_kedaluwarsa(): void
    {
        $partner = new Tenant(['status' => Tenant::AKTIF, 'expires_at' => null]);

        $this->assertFalse($partner->kedaluwarsa());
        $this->assertTrue($partner->isAktif());
        $this->assertSame('tanpa batas', $partner->sisaMasa());
    }

    public function test_hak_yang_tidak_dicentang_tidak_dimiliki(): void
    {
        $partner = new Tenant(['features' => ['landing', 'toko']]);

        $this->assertTrue($partner->punyaHak('toko'));
        $this->assertFalse($partner->punyaHak('produksi'));
    }

    /** @return array<string, string> */
    private function isian(array $ganti = []): array
    {
        return array_merge([
            'name' => 'Knalpot Jaya Motor',
            'slug' => 'knalpot-jaya',
            'owner_name' => 'Budi Santoso',
            'owner_email' => 'budi@contoh.test',
            'owner_phone' => '08123456789',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $ganti);
    }
}
