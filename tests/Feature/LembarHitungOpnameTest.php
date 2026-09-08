<?php

namespace Tests\Feature;

use App\Filament\Resources\MaterialOpnames\MaterialOpnameResource;
use App\Filament\Resources\StockOpnames\Pages\LembarHitung;
use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Material;
use App\Models\MaterialOpname;
use App\Models\MaterialOpnameItem;
use App\Models\Rack;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Models\Warehouse;
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

    public function test_lembar_bahan_kosong_bisa_dicetak_tanpa_sesi_apa_pun(): void
    {
        Material::create([
            'sku' => 'PIP-010',
            'name' => 'Pipa Uji Kosong',
            'dimension_type' => 'linear',
            'unit' => 'batang',
            'length_mm' => 6000,
        ]);

        $this->assertSame(0, MaterialOpname::count());

        $this->get(MaterialOpnameResource::getUrl('lembar-kosong'))
            ->assertOk()
            ->assertSee('Lembar Hitung Fisik Bahan')
            ->assertSee('Pipa Uji Kosong')
            ->assertSee('Belum Tercatat di Rak');
    }

    public function test_lembar_bahan_memuat_rak_dan_konversi_satuan(): void
    {
        $gudang = Warehouse::create(['name' => 'Gudang Produksi', 'code' => 'GP']);
        $rak = Rack::create(['warehouse_id' => $gudang->id, 'name' => 'Rak Pipa', 'code' => 'A1']);

        $bahan = Material::create([
            'sku' => 'PIP-304',
            'name' => 'Pipa Stainless 304 1.5 inch',
            'dimension_type' => 'linear',
            'unit' => 'batang',
            'length_mm' => 6000,
            'stock' => 30000,
        ]);

        $opname = MaterialOpname::create([
            'opname_number' => 'SOB-UJI-1',
            'opname_date' => now(),
            'warehouse_id' => $gudang->id,
            'counted_by' => 'Sigit',
            'status' => 'draft',
        ]);

        MaterialOpnameItem::create([
            'material_opname_id' => $opname->id,
            'material_id' => $bahan->id,
            'rack_id' => $rak->id,
            'system_qty' => 30000,
            'physical_qty' => 0,
        ]);

        $this->get(MaterialOpnameResource::getUrl('lembar-hitung', ['record' => $opname]))
            ->assertOk()
            ->assertSee('Lembar Hitung Fisik Bahan')
            ->assertSee('Pipa Stainless 304 1.5 inch')
            ->assertSee('Gudang Produksi / A1')
            ->assertSee('1 batang = 6 m')
            ->assertSee('Gudang Produksi');
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
