<?php

namespace Tests\Feature;

use App\Models\Formula;
use App\Models\Item;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\MaterialOpname;
use App\Models\MaterialOpnameItem;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseItem;
use App\Models\MaterialStock;
use App\Models\Production;
use App\Models\ProductionMaterial;
use App\Models\Rack;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Wallet;
use App\Models\Warehouse;
use App\Services\PostingService;
use App\Services\ProductionPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Modul produksi menyimpan dua satuan sekaligus — dibeli per batang, dipakai
 * per milimeter — lalu menurunkan HPP dari keduanya. Salah konversi di sini
 * tidak memunculkan error; yang muncul harga jual yang dihitung dari modal
 * yang keliru. Angkanya yang diuji, bukan sekadar jalannya.
 */
class ProductionPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductionPostingService $posting;

    private int $urut = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->posting = app(ProductionPostingService::class);
    }

    // -------------------------------------------------------- pembelian bahan

    public function test_pembelian_bahan_mengubah_satuan_beli_menjadi_satuan_dasar(): void
    {
        $bahan = $this->bahan();            // 1 batang = 6.000 mm
        $rak = $this->rak();
        $dompet = $this->dompet();

        $nota = $this->pembelianBahan($bahan, qty: 7, harga: 90_000, rak: $rak, dompet: $dompet);

        $this->posting->postPurchase($nota);

        $bahan->refresh();

        // Dibeli 7 batang, disimpan sebagai 42.000 mm supaya sisa potongan bisa dinyatakan.
        $this->assertSame(42_000.0, (float) $bahan->stock);
        // cost_price tetap per satuan BELI, harga per satuan dasar diturunkan.
        $this->assertSame(90_000.0, (float) $bahan->cost_price);
        $this->assertSame(15.0, $bahan->basePrice());

        $mutasi = MaterialMovement::sole();
        $this->assertSame(42_000.0, (float) $mutasi->qty_in);
        $this->assertSame(42_000.0, (float) $mutasi->balance_after);
        $this->assertSame(15.0, (float) $mutasi->unit_cost);

        $this->assertSame(42_000.0, (float) $this->isiRak($bahan, $rak));
        $this->assertSame(-630_000.0, (float) $dompet->refresh()->current_balance);
        $this->assertSame('posted', $nota->refresh()->status);
    }

    // --------------------------------------------------------------- produksi

    public function test_produksi_ditolak_saat_bahan_kurang_tanpa_meninggalkan_jejak(): void
    {
        $bahan = $this->bahan();
        $rak = $this->rak();
        $this->posting->postPurchase($this->pembelianBahan($bahan, qty: 1, harga: 90_000, rak: $rak));

        $barangJadi = $this->barangJadi();
        $produksi = $this->produksi($this->formula($barangJadi, 2), $bahan, butuh: 12_000);

        $sebelumMutasi = MaterialMovement::count();

        try {
            $this->posting->postProduction($produksi);
            $this->fail('Produksi melebihi stok bahan seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak cukup', $e->getMessage());
        }

        $this->assertSame(6_000.0, (float) $bahan->refresh()->stock);
        $this->assertSame($sebelumMutasi, MaterialMovement::count());
        $this->assertSame(0.0, (float) $barangJadi->refresh()->stock);
        $this->assertSame('draft', $produksi->refresh()->status);
    }

    public function test_produksi_menghitung_hpp_dan_memasukkan_barang_jadi_ke_inventory(): void
    {
        $bahan = $this->bahan();
        $rak = $this->rak();
        $this->posting->postPurchase($this->pembelianBahan($bahan, qty: 7, harga: 90_000, rak: $rak));

        $barangJadi = $this->barangJadi();
        $produksi = $this->produksi($this->formula($barangJadi, 2), $bahan, butuh: 12_000);

        $this->posting->postProduction($produksi);

        $produksi->refresh();

        // 12.000 mm x Rp15/mm = Rp180.000; tanpa jasa, mesin, dan overhead.
        $this->assertSame(180_000.0, (float) $produksi->material_cost);
        $this->assertSame(0.0, (float) $produksi->service_cost);
        $this->assertSame(0.0, (float) $produksi->machine_cost);
        $this->assertSame(0.0, (float) $produksi->overhead_cost);
        $this->assertSame(180_000.0, (float) $produksi->total_cost);
        // 2 unit keluar dari satu nota, jadi modal per unit separuhnya.
        $this->assertSame(90_000.0, (float) $produksi->hpp_per_unit);
        $this->assertSame('posted', $produksi->status);

        // Bahan berkurang, barang jadi masuk dengan harga pokok = HPP.
        $this->assertSame(30_000.0, (float) $bahan->refresh()->stock);
        $this->assertSame(2.0, (float) $barangJadi->refresh()->stock);
        $this->assertSame(90_000.0, (float) $barangJadi->cost_price);
        $this->assertSame(30_000.0, (float) $this->isiRak($bahan, $rak));
    }

    public function test_pemakaian_bahan_dipecah_lintas_rak_dimulai_dari_yang_terbanyak(): void
    {
        $bahan = $this->bahan();
        $rakBesar = $this->rak('A1');
        $rakKecil = $this->rak('B1');

        $this->posting->postPurchase($this->pembelianBahan($bahan, qty: 5, harga: 90_000, rak: $rakBesar));
        $this->posting->postPurchase($this->pembelianBahan($bahan, qty: 2, harga: 90_000, rak: $rakKecil));
        $this->assertSame(30_000.0, (float) $this->isiRak($bahan, $rakBesar));
        $this->assertSame(12_000.0, (float) $this->isiRak($bahan, $rakKecil));

        $produksi = $this->produksi($this->formula($this->barangJadi(), 1), $bahan, butuh: 35_000);
        $this->posting->postProduction($produksi);

        // Rak terbanyak dikuras dulu, sisanya baru diambil dari rak kedua.
        $this->assertSame(0.0, (float) $this->isiRak($bahan, $rakBesar));
        $this->assertSame(7_000.0, (float) $this->isiRak($bahan, $rakKecil));
        $this->assertSame(7_000.0, (float) $bahan->refresh()->stock);

        $keluar = MaterialMovement::where('type', 'production')->get();
        $this->assertCount(2, $keluar);
        $this->assertSame(35_000.0, (float) $keluar->sum('qty_out'));
    }

    public function test_pembatalan_produksi_ditolak_bila_barang_jadinya_sudah_terjual(): void
    {
        $bahan = $this->bahan();
        $this->posting->postPurchase($this->pembelianBahan($bahan, qty: 7, harga: 90_000, rak: $this->rak()));

        $barangJadi = $this->barangJadi();
        $produksi = $this->produksi($this->formula($barangJadi, 2), $bahan, butuh: 12_000);
        $this->posting->postProduction($produksi);
        $this->assertSame(2.0, (float) $barangJadi->refresh()->stock);

        // Satu unit terjual; tinggal 1, sedangkan nota ini menyumbang 2.
        $this->jualSatu($barangJadi);
        $this->assertSame(1.0, (float) $barangJadi->refresh()->stock);

        try {
            $this->posting->unpostProduction($produksi->refresh());
            $this->fail('Pembatalan seharusnya ditolak karena akan membuat stok minus.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sudah terjual', $e->getMessage());
        }

        $this->assertSame('posted', $produksi->refresh()->status);
        $this->assertSame(1.0, (float) $barangJadi->refresh()->stock);
    }

    // ---------------------------------------------------------- opname bahan

    public function test_opname_bahan_mengoreksi_stok_dan_isi_rak(): void
    {
        $bahan = $this->bahan();
        $rak = $this->rak();
        $this->posting->postPurchase($this->pembelianBahan($bahan, qty: 7, harga: 90_000, rak: $rak));

        $opname = MaterialOpname::create([
            'opname_number' => 'SOB-1',
            'opname_date' => now(),
            'status' => 'draft',
        ]);

        MaterialOpnameItem::create([
            'material_opname_id' => $opname->id,
            'material_id' => $bahan->id,
            'rack_id' => $rak->id,
            'system_qty' => 42_000,
            'physical_qty' => 40_000,   // hilang 2.000 mm
        ]);

        $this->posting->postMaterialOpname($opname);

        $this->assertSame(40_000.0, (float) $bahan->refresh()->stock);
        $this->assertSame(40_000.0, (float) $this->isiRak($bahan, $rak));
        $this->assertSame('posted', $opname->refresh()->status);

        $koreksi = MaterialMovement::where('type', 'opname')->sole();
        $this->assertSame(2_000.0, (float) $koreksi->qty_out);
        $this->assertSame($rak->id, $koreksi->rack_id);
    }

    // --------------------------------------------------------------- fixture

    /** Pipa linear: 1 batang = 6.000 mm, jadi Rp90.000/batang = Rp15/mm. */
    private function bahan(): Material
    {
        return Material::create([
            'sku' => sprintf('BHN-%03d', ++$this->urut),
            'name' => 'Pipa Stainless Uji '.$this->urut,
            'dimension_type' => 'linear',
            'unit' => 'batang',
            'length_mm' => 6_000,
            'cost_price' => 0,
            'stock' => 0,
        ]);
    }

    private function rak(string $kode = 'A1'): Rack
    {
        $gudang = Warehouse::firstOrCreate(
            ['code' => 'GU'],
            ['name' => 'Gudang Uji']
        );

        return Rack::create([
            'warehouse_id' => $gudang->id,
            'name' => 'Rak '.$kode,
            'code' => $kode,
        ]);
    }

    private function dompet(): Wallet
    {
        return Wallet::create([
            'name' => 'Kas Uji '.++$this->urut,
            'type' => 'cash',
            'opening_balance' => 0,
            'current_balance' => 0,
        ]);
    }

    private function barangJadi(): Item
    {
        return Item::create([
            'sku' => sprintf('JD-%03d', ++$this->urut),
            'name' => 'Knalpot Jadi '.$this->urut,
            'unit' => 'pcs',
            'stock' => 0,
            'cost_price' => 0,
        ]);
    }

    private function pembelianBahan(Material $bahan, float $qty, float $harga, Rack $rak, ?Wallet $dompet = null): MaterialPurchase
    {
        $nota = MaterialPurchase::create([
            'invoice_number' => sprintf('PBB-%03d', ++$this->urut),
            'purchased_at' => now(),
            'wallet_id' => $dompet?->id,
            'status' => 'draft',
        ]);

        MaterialPurchaseItem::create([
            'material_purchase_id' => $nota->id,
            'material_id' => $bahan->id,
            'rack_id' => $rak->id,
            'qty' => $qty,
            'unit_cost' => $harga,
            'subtotal' => $qty * $harga,
        ]);

        return $nota->refresh();
    }

    private function formula(Item $barangJadi, float $hasil): Formula
    {
        return Formula::create([
            'code' => sprintf('F-%03d', ++$this->urut),
            'name' => 'Formula Uji '.$this->urut,
            'item_id' => $barangJadi->id,
            'output_qty' => $hasil,
            'output_unit' => 'pcs',
            'is_active' => true,
        ]);
    }

    private function produksi(Formula $formula, Material $bahan, float $butuh): Production
    {
        $nota = Production::create([
            'production_number' => sprintf('PRD-%03d', ++$this->urut),
            'produced_at' => now(),
            'formula_id' => $formula->id,
            'batch_qty' => 1,
            'output_qty' => $formula->output_qty,
            'status' => 'draft',
        ]);

        ProductionMaterial::create([
            'production_id' => $nota->id,
            'material_id' => $bahan->id,
            'qty' => $butuh,
            'unit_cost' => 0,
        ]);

        return $nota->refresh();
    }

    private function jualSatu(Item $barang): void
    {
        $nota = Sale::create([
            'invoice_number' => sprintf('PJ-%03d', ++$this->urut),
            'sold_at' => now(),
            'status' => 'draft',
        ]);

        SaleItem::create([
            'sale_id' => $nota->id,
            'item_id' => $barang->id,
            'qty' => 1,
            'unit_price' => 200_000,
            'subtotal' => 200_000,
        ]);

        app(PostingService::class)->postSale($nota->refresh());
    }

    private function isiRak(Material $bahan, Rack $rak): float
    {
        return (float) MaterialStock::where('material_id', $bahan->id)
            ->where('rack_id', $rak->id)
            ->value('qty');
    }
}
