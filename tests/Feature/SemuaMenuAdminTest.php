<?php

namespace Tests\Feature;

use App\Filament\Resources\ExhaustComponents\ExhaustComponentResource;
use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Filament\Resources\ExpenseTypes\ExpenseTypeResource;
use App\Filament\Resources\ItemCategories\ItemCategoryResource;
use App\Filament\Resources\ItemCategories\Pages\ListItemCategories;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\ItemTypes\ItemTypeResource;
use App\Filament\Resources\MotorcycleBrands\MotorcycleBrandResource;
use App\Filament\Resources\MotorcycleModels\MotorcycleModelResource;
use App\Filament\Resources\PriceTiers\PriceTierResource;
use App\Filament\Resources\ProductionItemCategories\ProductionItemCategoryResource;
use App\Filament\Resources\ProductionItems\ProductionItemResource;
use App\Filament\Resources\ProductionServices\ProductionServiceResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Wallets\WalletResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\ItemCategory;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Buka setiap menu admin, satu per satu.
 *
 * Ada di sini karena kelas kesalahan yang nyata: menghapus sebuah model lalu
 * meninggalkan satu berkas yang masih memanggilnya. Lint lolos, suite lolos,
 * dan yang menemukan justru pengguna — layar putih bertuliskan "Failed to open
 * stream". Test ini menyentuh tiap halaman daftar, jadi rujukan yang menggantung
 * ketahuan sebelum sampai ke orang.
 */
class SemuaMenuAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Master data — form pendek, ditambah dan diubah lewat modal.
     *
     * Nota bertingkat (pembelian, penjualan, opname) dan formula sengaja tidak
     * di sini: barisnya banyak dan butuh selebar halaman.
     *
     * @var array<int, class-string<\Filament\Resources\Resource>>
     */
    private const MASTER_DATA = [
        ExhaustComponentResource::class,
        ExpenseCategoryResource::class,
        ExpenseTypeResource::class,
        ItemCategoryResource::class,
        ItemResource::class,
        ItemTypeResource::class,
        MotorcycleBrandResource::class,
        MotorcycleModelResource::class,
        PriceTierResource::class,
        ProductionItemCategoryResource::class,
        ProductionItemResource::class,
        ProductionServiceResource::class,
        ProductResource::class,
        WalletResource::class,
        WarehouseResource::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_setiap_halaman_daftar_resource_bisa_dibuka(): void
    {
        $resources = Filament::getPanel('admin')->getResources();

        $this->assertNotEmpty($resources, 'Panel admin tidak punya resource sama sekali.');

        foreach ($resources as $resource) {
            $url = $resource::getUrl('index');
            $balasan = $this->get($url);

            $this->assertTrue(
                $balasan->isSuccessful(),
                sprintf('Menu %s (%s) balas HTTP %d.', class_basename($resource), $url, $balasan->status())
            );
        }
    }

    public function test_setiap_halaman_mandiri_bisa_dibuka(): void
    {
        foreach (Filament::getPanel('admin')->getPages() as $page) {
            $url = $page::getUrl();
            $balasan = $this->get($url);

            $this->assertTrue(
                $balasan->isSuccessful(),
                sprintf('Halaman %s (%s) balas HTTP %d.', class_basename($page), $url, $balasan->status())
            );
        }
    }

    /**
     * Master data ditambah dan diubah lewat modal, bukan halaman sendiri —
     * mengisi satu nama kategori tidak sepadan dengan berpindah halaman lalu
     * kembali lagi.
     *
     * Halaman create/edit yang tersisa akan membuat tombolnya kembali jadi
     * tautan, dan itu berlangsung tanpa error apa pun.
     */
    public function test_master_data_tidak_punya_halaman_tambah_dan_ubah(): void
    {
        foreach (self::MASTER_DATA as $resource) {
            $nama = class_basename($resource);

            $this->assertFalse($resource::hasPage('create'), "$nama masih punya halaman tambah sendiri.");
            $this->assertFalse($resource::hasPage('edit'), "$nama masih punya halaman ubah sendiri.");
        }
    }

    /**
     * Dan modalnya benar-benar menyimpan — halaman yang hilang tidak ada
     * gunanya kalau penggantinya cuma terbuka lalu diam.
     */
    public function test_master_data_bisa_ditambah_lewat_modal(): void
    {
        Livewire::test(ListItemCategories::class)
            ->callAction('create', [
                'name' => 'Knalpot Racing',
                'slug' => 'knalpot-racing',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('item_categories', ['slug' => 'knalpot-racing']);
    }

    public function test_master_data_bisa_diubah_lewat_modal(): void
    {
        $kategori = ItemCategory::create(['name' => 'Lama', 'slug' => 'lama']);

        Livewire::test(ListItemCategories::class)
            ->callTableAction('edit', $kategori, ['name' => 'Baru'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Baru', $kategori->fresh()->name);
    }
}
