<?php

namespace Tests\Feature;

use App\Models\ProductionItem;
use App\Models\ProductionPurchase;
use App\Models\ProductionPurchaseItem;
use App\Models\Wallet;
use App\Models\Warehouse;
use App\Services\ProductionStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Pembelian bahan produksi: stok gudang naik, kas turun.
 *
 * Yang paling mudah salah di sini adalah satuannya. Nota toko menyebut "7
 * batang", sedangkan stok disimpan dalam milimeter — dan membukukan angka 7
 * apa adanya membuat stok pipa terbaca 7 mm, bukan 42 meter.
 */
class PembelianBahanTest extends TestCase
{
    use RefreshDatabase;

    private ProductionStockService $stok;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stok = app(ProductionStockService::class);
    }

    public function test_qty_satuan_beli_dikonversi_ke_satuan_pakai(): void
    {
        $pipa = $this->pipa();
        $gudang = $this->gudang();
        $nota = $this->nota($gudang);

        // 7 batang @ 6.000 mm = 42.000 mm
        $this->baris($nota, $pipa, qty: 7, harga: 90_000);

        $this->stok->postPurchase($nota);

        $this->assertSame(42_000.0, (float) $pipa->fresh()->stock);
        $this->assertSame(42_000.0, $pipa->fresh()->stockIn($gudang->id));
    }

    public function test_kas_berkurang_sebesar_total_nota(): void
    {
        $dompet = Wallet::create(['name' => 'Kas Bengkel', 'opening_balance' => 1_000_000, 'current_balance' => 1_000_000]);
        $nota = $this->nota($this->gudang(), ['wallet_id' => $dompet->id, 'shipping_cost' => 25_000, 'discount' => 5_000]);

        $this->baris($nota, $this->pipa(), qty: 2, harga: 90_000);

        $this->stok->postPurchase($nota);

        // 180.000 - 5.000 + 25.000 = 200.000
        $this->assertSame(200_000.0, (float) $nota->fresh()->total);
        $this->assertSame(800_000.0, (float) $dompet->fresh()->current_balance);
    }

    /** Nota tanpa dompet tetap menambah stok — kas memang belum bergerak. */
    public function test_tanpa_dompet_stok_tetap_naik_kas_tidak_tersentuh(): void
    {
        $pipa = $this->pipa();
        $nota = $this->nota($this->gudang());
        $this->baris($nota, $pipa, qty: 1, harga: 90_000);

        $this->stok->postPurchase($nota);

        $this->assertSame(6_000.0, (float) $pipa->fresh()->stock);
        $this->assertSame(0, $nota->walletTransactions()->count());
    }

    /** Harga beli terakhir menjadi harga pokok berjalan. */
    public function test_harga_beli_terakhir_memperbarui_harga_pokok(): void
    {
        $pipa = $this->pipa();
        $nota = $this->nota($this->gudang());
        $this->baris($nota, $pipa, qty: 1, harga: 120_000);

        $this->stok->postPurchase($nota);

        $this->assertSame(120_000.0, (float) $pipa->fresh()->cost_price);
        $this->assertSame(20.0, $pipa->fresh()->basePrice());   // 120.000 / 6.000 mm
    }

    public function test_membatalkan_mengembalikan_stok_dan_kas(): void
    {
        $dompet = Wallet::create(['name' => 'Kas Bengkel', 'opening_balance' => 500_000, 'current_balance' => 500_000]);
        $pipa = $this->pipa();
        $nota = $this->nota($this->gudang(), ['wallet_id' => $dompet->id]);
        $this->baris($nota, $pipa, qty: 3, harga: 90_000);

        $this->stok->postPurchase($nota);
        $this->stok->unpostPurchase($nota);

        $this->assertSame(0.0, (float) $pipa->fresh()->stock);
        $this->assertSame(500_000.0, (float) $dompet->fresh()->current_balance);
        $this->assertSame('draft', $nota->fresh()->status);
        $this->assertSame(0, $nota->movements()->count());
    }

    public function test_nota_yang_sudah_dibukukan_tidak_bisa_dibukukan_dua_kali(): void
    {
        $nota = $this->nota($this->gudang());
        $this->baris($nota, $this->pipa(), qty: 1, harga: 90_000);
        $this->stok->postPurchase($nota);

        $this->expectException(RuntimeException::class);
        $this->stok->postPurchase($nota->fresh());
    }

    public function test_nota_tanpa_baris_ditolak(): void
    {
        $this->expectExceptionMessage('tanpa baris bahan');

        $this->stok->postPurchase($this->nota($this->gudang()));
    }

    /**
     * Satu nota toko bisa memuat pipa untuk gudang bahan mentah dan baut untuk
     * gudang lain. Memaksa satu gudang untuk seluruh nota membuat orang
     * memecah notanya, dan nota yang dipecah tidak lagi cocok dengan kertasnya.
     */
    public function test_tiap_baris_masuk_ke_gudang_yang_dipilihnya_sendiri(): void
    {
        $mentah = $this->gudang('BM');
        $sisa = $this->gudang('BS');

        $pipa = $this->pipa();
        $baut = ProductionItem::create([
            'sku' => 'BAUT-M8', 'name' => 'Baut M8',
            'shape' => 'count', 'unit' => 'pcs', 'cost_price' => 1_500,
        ]);

        $nota = $this->nota($mentah);
        $this->baris($nota, $pipa, qty: 2, harga: 90_000, gudang: $mentah);
        $this->baris($nota, $baut, qty: 50, harga: 1_500, gudang: $sisa);

        $this->stok->postPurchase($nota);

        $this->assertSame(12_000.0, $pipa->fresh()->stockIn($mentah->id));
        $this->assertSame(0.0, $pipa->fresh()->stockIn($sisa->id));

        $this->assertSame(50.0, $baut->fresh()->stockIn($sisa->id));
        $this->assertSame(0.0, $baut->fresh()->stockIn($mentah->id));
    }

    /** Baris yang belum menyebut gudang ikut gudang bawaan notanya. */
    public function test_baris_tanpa_gudang_ikut_gudang_bawaan_nota(): void
    {
        $mentah = $this->gudang('BM');
        $pipa = $this->pipa();

        $nota = $this->nota($mentah);
        $this->baris($nota, $pipa, qty: 1, harga: 90_000);   // tanpa gudang

        $this->stok->postPurchase($nota);

        $this->assertSame(6_000.0, $pipa->fresh()->stockIn($mentah->id));
    }

    /** Tanpa gudang di baris maupun di nota, tidak ada tempat yang bisa dituju. */
    public function test_baris_tanpa_gudang_dan_tanpa_bawaan_ditolak(): void
    {
        $nota = $this->nota($this->gudang());
        $nota->forceFill(['warehouse_id' => null])->save();
        $this->baris($nota, $this->pipa(), qty: 1, harga: 90_000);

        $this->expectExceptionMessage('belum menyebut gudang tujuan');

        $this->stok->postPurchase($nota->fresh());
    }

    // --------------------------------------------------------------- fixture

    private function nota(Warehouse $gudang, array $atribut = []): ProductionPurchase
    {
        return ProductionPurchase::create(array_merge([
            'invoice_number' => 'PBP-'.fake()->unique()->numerify('######'),
            'purchased_at' => now()->toDateString(),
            'warehouse_id' => $gudang->id,
        ], $atribut));
    }

    private function baris(
        ProductionPurchase $nota,
        ProductionItem $bahan,
        float $qty,
        float $harga,
        ?Warehouse $gudang = null,
    ): ProductionPurchaseItem {
        return ProductionPurchaseItem::create([
            'production_purchase_id' => $nota->id,
            'production_item_id' => $bahan->id,
            'warehouse_id' => $gudang?->id,
            'qty' => $qty,
            'unit_cost' => $harga,
        ]);
    }

    private function pipa(): ProductionItem
    {
        return ProductionItem::create([
            'sku' => 'PIP-001',
            'name' => 'Pipa SS 201 Ø28',
            'shape' => 'linear',
            'unit' => 'batang',
            'length_mm' => 6_000,
            'cost_price' => 90_000,
        ]);
    }

    /** Keempat gudang produksi sudah ada sejak migration. */
    private function gudang(string $kode = 'BM'): Warehouse
    {
        return Warehouse::where('code', $kode)->sole();
    }
}
