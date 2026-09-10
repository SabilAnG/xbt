<?php

namespace Tests\Feature;

use App\Filament\Resources\StockOpnames\Pages\LembarHitung;
use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lembar hitung dicetak sebelum orang berangkat ke gudang. Kalau rusak, yang
 * ketahuan bukan errornya melainkan petugas yang sudah berdiri di depan rak
 * tanpa kertas — jadi rendernya dijaga di sini.
 */
class LembarHitungOpnameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_lembar_aset_memuat_barang_dari_sesi(): void
    {
        $kategori = ItemCategory::create(['name' => 'Knalpot Racing', 'slug' => 'knalpot-racing']);

        $item = Item::create([
            'sku' => 'KNL-001',
            'name' => 'Silencer Bulat 3 inch',
            'item_category_id' => $kategori->id,
            'unit' => 'pcs',
            'stock' => 12,
        ]);

        $opname = $this->sesiAset();

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'item_id' => $item->id,
            'system_qty' => 12,
            'physical_qty' => 0,
        ]);

        $this->get(StockOpnameResource::getUrl('lembar-hitung', ['record' => $opname]))
            ->assertOk()
            ->assertSee('Lembar Hitung Fisik')
            ->assertSee('SO-UJI-1')
            ->assertSee('Silencer Bulat 3 inch')
            ->assertSee('KNL-001')
            ->assertSee('Knalpot Racing')
            ->assertSee('Hitung Fisik');
    }

    /** Sesi draft yang belum diisi tetap harus keluar kertasnya. */
    public function test_lembar_aset_kosong_jatuh_ke_seluruh_barang_aktif(): void
    {
        Item::create(['sku' => 'KNL-002', 'name' => 'Elbow Stainless', 'unit' => 'pcs']);
        Item::create(['sku' => 'KNL-003', 'name' => 'Peredam Glasswool', 'unit' => 'pcs', 'is_active' => false]);

        $opname = $this->sesiAset();

        $this->get(StockOpnameResource::getUrl('lembar-hitung', ['record' => $opname]))
            ->assertOk()
            ->assertSee('Elbow Stainless')
            ->assertDontSee('Peredam Glasswool')
            ->assertSee('Tanpa Kategori');
    }

    /** Bawaannya blind count: kolom stok sistem tidak ikut tercetak. */
    public function test_stok_sistem_disembunyikan_lalu_bisa_ditampilkan(): void
    {
        $item = Item::create(['sku' => 'KNL-004', 'name' => 'Pipa Header', 'unit' => 'pcs', 'stock' => 7]);
        $opname = $this->sesiAset();

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'item_id' => $item->id,
            'system_qty' => 7,
            'physical_qty' => 0,
        ]);

        Livewire::test(LembarHitung::class, ['record' => $opname])
            ->assertSuccessful()
            ->assertDontSee('Stok Sistem</th>', escape: false)
            ->set('tampilkanSistem', true)
            ->assertSee('Stok Sistem</th>', escape: false)
            ->assertSee('Selisih</th>', escape: false);
    }

    /**
     * Alur nyatanya: kertas dicetak DULU, dibawa ke gudang, sesinya menyusul.
     * Jadi lembar harus keluar walau belum ada satu pun sesi opname.
     */
    public function test_lembar_aset_kosong_bisa_dicetak_tanpa_sesi_apa_pun(): void
    {
        Item::create(['sku' => 'KNL-010', 'name' => 'Silencer Oval', 'unit' => 'pcs']);

        $this->assertSame(0, StockOpname::count());

        $this->get(StockOpnameResource::getUrl('lembar-kosong'))
            ->assertOk()
            ->assertSee('Lembar Hitung Fisik')
            ->assertSee('Silencer Oval')
            ->assertSee('Hitung Fisik');
    }

    /**
     * Banyaknya temuan tak terdaftar berbeda tiap gudang, jadi ruang tulisnya
     * harus bisa ditambah sebelum kertasnya keluar dari printer.
     */
    public function test_baris_kosong_bisa_diatur_sebelum_dicetak(): void
    {
        // Satu barang tanpa kategori berarti tepat satu kelompok, sehingga
        // jumlah baris kosong yang tercetak bisa dihitung persis.
        Item::create(['sku' => 'KNL-020', 'name' => 'Silencer Panjang', 'unit' => 'pcs']);

        $lembar = Livewire::test(LembarHitung::class)->assertSuccessful();

        // Tombol pengaturnya harus benar-benar terlihat, bukan sekadar ada di
        // kode — kalau labelnya tidak terender, fiturnya tidak terpakai.
        $lembar->assertSee('Baris Kosong: 2');

        // Penanda ini hanya dipakai baris kosong saat stok sistem disembunyikan.
        $this->assertSame(2, substr_count($lembar->html(), 'num kotak'));

        $lembar->set('barisKosong', 8);
        $this->assertSame(8, substr_count($lembar->html(), 'num kotak'));

        $lembar->set('barisKosong', 0);
        $this->assertSame(0, substr_count($lembar->html(), 'num kotak'));
    }

    private function sesiAset(): StockOpname
    {
        return StockOpname::create([
            'opname_number' => 'SO-UJI-1',
            'opname_date' => now(),
            'counted_by' => 'Budi',
            'status' => 'draft',
        ]);
    }
}
