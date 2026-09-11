<?php

namespace Tests\Feature;

use App\Filament\Resources\Items\Schemas\ItemForm;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Services\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mendaftarkan barang baru dan memperbarui harganya dari layar stok opname.
 *
 * Orang yang sedang berdiri di depan rak adalah orang yang paling tahu apa yang
 * ada di sana dan berapa harganya. Menyuruhnya menutup hitungan, membuka menu
 * lain, lalu mengulang dari awal adalah cara paling ampuh membuat hitungan itu
 * tidak pernah selesai dicatat.
 */
class OpnameBarangBaruTest extends TestCase
{
    use RefreshDatabase;

    private PostingService $posting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->posting = app(PostingService::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_barang_baru_dari_opname_masuk_ke_master(): void
    {
        $honda = MotorcycleBrand::create(['name' => 'Honda', 'slug' => 'honda']);
        $beat = MotorcycleModel::create([
            'motorcycle_brand_id' => $honda->id, 'name' => 'Beat Street', 'slug' => 'honda-beat-street',
        ]);
        $kategori = ItemCategory::create(['name' => 'Full Set', 'slug' => 'full-set']);

        $id = ItemForm::buat([
            'name' => 'Knalpot Racing Beat Street',
            'sku' => 'KR-BEAT-01',
            'item_category_id' => $kategori->id,
            'motorcycleModels' => [$beat->id],
            'unit' => 'pcs',
            'sell_price' => 850_000,
        ]);

        $barang = Item::findOrFail($id);

        $this->assertSame('KR-BEAT-01', $barang->sku);
        $this->assertSame(850_000.0, (float) $barang->sell_price);
        $this->assertTrue($barang->is_active);

        // Stok sengaja nol — hitungan fisiklah yang mengisinya.
        $this->assertSame(0.0, (float) $barang->stock);

        // Type motor relasi banyak-ke-banyak, jadi tidak ikut create() begitu saja.
        $this->assertTrue($barang->motorcycleModels->contains($beat));
    }

    /** Type motor baru yang dibuat dari opname juga masuk master motor. */
    public function test_type_motor_baru_tersimpan_berikut_brandnya(): void
    {
        $yamaha = MotorcycleBrand::create(['name' => 'Yamaha', 'slug' => 'yamaha']);

        $type = MotorcycleModel::create([
            'motorcycle_brand_id' => $yamaha->id,
            'name' => 'Aerox 155',
            'slug' => 'yamaha-aerox-155',
        ]);

        $this->assertSame('Yamaha Aerox 155', $type->fullName());
        $this->assertDatabaseHas('motorcycle_models', ['slug' => 'yamaha-aerox-155']);
    }

    public function test_harga_yang_diisi_saat_opname_memperbarui_harga_barang(): void
    {
        $barang = $this->barang(['sell_price' => 500_000, 'stock' => 10]);
        $opname = $this->opname();

        $this->baris($opname, $barang, fisik: 12, harga: 650_000);

        $this->posting->postOpname($opname);

        $this->assertSame(650_000.0, (float) $barang->fresh()->sell_price);
        $this->assertSame(12.0, (float) $barang->fresh()->stock);
    }

    /**
     * Harga diperbarui walau jumlahnya cocok — yang dikoreksi harganya, bukan
     * stoknya, dan baris tanpa selisih tetap punya alasan untuk ada.
     */
    public function test_harga_tetap_diperbarui_walau_jumlahnya_cocok(): void
    {
        $barang = $this->barang(['sell_price' => 500_000, 'stock' => 10]);
        $opname = $this->opname();

        $this->baris($opname, $barang, fisik: 10, harga: 750_000);

        $this->posting->postOpname($opname);

        $this->assertSame(750_000.0, (float) $barang->fresh()->sell_price);
        $this->assertSame(10.0, (float) $barang->fresh()->stock);
    }

    /** Harga yang dikosongkan tidak menyentuh harga apa pun. */
    public function test_harga_kosong_membiarkan_harga_lama(): void
    {
        $barang = $this->barang(['sell_price' => 500_000, 'stock' => 10]);
        $opname = $this->opname();

        $this->baris($opname, $barang, fisik: 8, harga: null);

        $this->posting->postOpname($opname);

        $this->assertSame(500_000.0, (float) $barang->fresh()->sell_price);
        $this->assertSame(8.0, (float) $barang->fresh()->stock);
    }

    // --------------------------------------------------------------- fixture

    private function barang(array $atribut = []): Item
    {
        return Item::create(array_merge([
            'sku' => 'KR-001',
            'name' => 'Knalpot Racing',
            'unit' => 'pcs',
            'cost_price' => 300_000,
        ], $atribut));
    }

    private function opname(): StockOpname
    {
        return StockOpname::create([
            'opname_number' => 'SO-TEST-01',
            'opname_date' => now()->toDateString(),
        ]);
    }

    private function baris(StockOpname $opname, Item $barang, float $fisik, ?float $harga): StockOpnameItem
    {
        return StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'item_id' => $barang->id,
            'system_qty' => (float) $barang->stock,
            'physical_qty' => $fisik,
            'unit_price' => $harga,
        ]);
    }
}
