<?php

namespace Tests\Feature;

use App\Filament\Pages\KalkulatorHpp;
use App\Filament\Resources\Advertisements\AdvertisementResource;
use App\Filament\Resources\Formulas\FormulaResource;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\ItemTypes\ItemTypeResource;
use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Resources\ProductionItemCategories\ProductionItemCategoryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Wallets\WalletResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\Tenant;
use App\Support\HakPartner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu apa saja yang boleh dibuka seorang partner.
 *
 * Ini pagar keamanan, bukan kerapian tampilan: `canAccess()` di Filament
 * menentukan menu muncul di navigasi DAN halamannya boleh dibuka. Yang
 * disembunyikan dari navigasi tapi masih bisa dibuka lewat alamat langsung
 * bukan penjagaan sama sekali.
 */
class PartnerHakMenuTest extends TestCase
{
    use RefreshDatabase;

    /** Menyalakan tenancy tanpa membuat database — yang diuji aturannya. */
    private function sebagaiPartner(array $fitur): Tenant
    {
        $partner = new Tenant([
            'id' => 'uji',
            'name' => 'Knalpot Jaya',
            'status' => Tenant::AKTIF,
            'features' => $fitur,
            'expires_at' => now()->addWeek(),
        ]);

        app()->instance('currentTenant', $partner);
        tenancy()->tenant = $partner;

        return $partner;
    }

    protected function tearDown(): void
    {
        tenancy()->tenant = null;

        parent::tearDown();
    }

    public function test_di_panel_pusat_semua_menu_terbuka(): void
    {
        $this->assertTrue(FormulaResource::canAccess());
        $this->assertTrue(PartnerResource::canAccess());
        $this->assertTrue(AdvertisementResource::canAccess());
        $this->assertTrue(KalkulatorHpp::canAccess());
    }

    public function test_partner_hanya_membuka_grup_yang_dicentang(): void
    {
        $this->sebagaiPartner(['landing', 'toko']);

        // Dicentang.
        $this->assertTrue(ProductResource::canAccess(), 'Toko Online seharusnya terbuka.');

        // Tidak dicentang.
        $this->assertFalse(FormulaResource::canAccess(), 'Produksi seharusnya tertutup.');
        $this->assertFalse(ItemResource::canAccess(), 'Operasional seharusnya tertutup.');
        $this->assertFalse(WalletResource::canAccess(), 'Aset seharusnya tertutup.');
        $this->assertFalse(ItemTypeResource::canAccess(), 'Master Data seharusnya tertutup.');
    }

    public function test_halaman_custom_ikut_disaring(): void
    {
        $this->sebagaiPartner(['landing']);
        $this->assertFalse(KalkulatorHpp::canAccess());

        $this->sebagaiPartner(['produksi']);
        $this->assertTrue(KalkulatorHpp::canAccess());
    }

    /**
     * Partner tidak punya partner, dan tidak menjual ruang iklan di situs kami.
     * Ini berlaku walau semua hak dicentang.
     */
    public function test_menu_milik_pusat_tertutup_walau_semua_hak_dicentang(): void
    {
        $this->sebagaiPartner(array_keys(Tenant::FEATURES));

        $this->assertFalse(PartnerResource::canAccess());
        $this->assertFalse(AdvertisementResource::canAccess());
    }

    /**
     * Memecah menu Produksi jadi dua grup adalah urusan tata letak. Partner
     * yang haknya sama sebelum dan sesudah pemecahan harus melihat menu yang
     * sama persis — grup yang tidak dikenal ditolak, jadi grup baru yang lupa
     * dipetakan akan mencabut gudang dan jenis barang tanpa suara.
     */
    public function test_pengaturan_produksi_memakai_hak_produksi_yang_sama(): void
    {
        $this->sebagaiPartner(['produksi']);

        $this->assertTrue(WarehouseResource::canAccess(), 'Gudang seharusnya ikut hak produksi.');
        $this->assertTrue(ProductionItemCategoryResource::canAccess(), 'Jenis Barang seharusnya ikut hak produksi.');

        $this->sebagaiPartner(['landing']);

        $this->assertFalse(WarehouseResource::canAccess());
        $this->assertFalse(ProductionItemCategoryResource::canAccess());
    }

    /**
     * Menu baru yang lupa didaftarkan lebih baik hilang dari panel partner
     * daripada diam-diam terbuka untuk semua orang.
     */
    public function test_grup_yang_tidak_dikenal_ditolak(): void
    {
        $this->sebagaiPartner(array_keys(Tenant::FEATURES));

        $this->assertFalse(HakPartner::boleh('App\Filament\Resources\MenuBaru', 'Grup Entah'));
        $this->assertFalse(HakPartner::boleh('App\Filament\Resources\MenuBaru', null));
    }

    public function test_seluruh_grup_navigasi_punya_pasangan_hak(): void
    {
        // Grup yang dipakai resource tapi belum ada di peta akan hilang diam-diam
        // dari panel partner. Lebih baik ketahuan di sini.
        $dipakai = ['Operasional', 'Produksi', 'Pengaturan Produksi', 'Aset', 'Master Data', 'Toko Online', 'Website', 'Partner'];

        foreach ($dipakai as $grup) {
            if ($grup === 'Partner') {
                continue;   // sengaja tidak pernah untuk partner
            }

            $this->assertArrayHasKey(
                $grup,
                HakPartner::GRUP_FITUR,
                "Grup [$grup] belum punya pasangan hak di HakPartner::GRUP_FITUR."
            );
        }

        // Dan tiap hak yang dipetakan harus benar-benar ada di daftar fitur.
        foreach (HakPartner::GRUP_FITUR as $grup => $fitur) {
            $this->assertArrayHasKey($fitur, Tenant::FEATURES, "Hak [$fitur] dari grup [$grup] tidak dikenal.");
        }
    }
}
