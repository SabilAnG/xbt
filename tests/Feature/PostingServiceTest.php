<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Wallet;
use App\Services\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Satu-satunya tempat yang boleh menulis stock_movements dan
 * wallet_transactions. Kalau kelas ini salah, tidak ada pesan error — yang
 * muncul hanya angka stok dan laba yang keliru lalu dipercaya. Jadi yang diuji
 * di sini bukan "tidak meledak", melainkan angkanya benar.
 */
class PostingServiceTest extends TestCase
{
    use RefreshDatabase;

    private PostingService $posting;

    private int $urut = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->posting = app(PostingService::class);
    }

    // ------------------------------------------------------------- pembelian

    public function test_pembelian_menambah_stok_memperbarui_harga_pokok_dan_mengurangi_dompet(): void
    {
        $barang = $this->barang(['cost_price' => 1000]);
        $dompet = $this->dompet(1_000_000);

        $nota = $this->pembelian($barang, qty: 10, harga: 1500, dompet: $dompet);

        $this->posting->postPurchase($nota);

        $this->assertSame(10.0, (float) $barang->refresh()->stock);
        // Harga pokok berjalan mengikuti biaya pembelian terakhir.
        $this->assertSame(1500.0, (float) $barang->cost_price);
        $this->assertSame('posted', $nota->refresh()->status);
        $this->assertSame(15_000.0, (float) $nota->total);
        $this->assertSame(985_000.0, (float) $dompet->refresh()->current_balance);

        $mutasi = StockMovement::where('item_id', $barang->id)->sole();
        $this->assertSame(10.0, (float) $mutasi->qty_in);
        $this->assertSame(0.0, (float) $mutasi->qty_out);
        $this->assertSame(10.0, (float) $mutasi->balance_after);
        $this->assertSame('purchase', $mutasi->type);
    }

    public function test_nota_yang_sudah_dibukukan_tidak_bisa_dibukukan_dua_kali(): void
    {
        $barang = $this->barang();
        $nota = $this->pembelian($barang, qty: 10, harga: 1000);

        $this->posting->postPurchase($nota);

        $this->expectException(RuntimeException::class);

        try {
            $this->posting->postPurchase($nota->refresh());
        } finally {
            // Yang penting bukan sekadar ada exception, tapi stoknya tidak dobel.
            $this->assertSame(10.0, (float) $barang->refresh()->stock);
            $this->assertSame(1, StockMovement::count());
        }
    }

    public function test_pembelian_tanpa_baris_barang_ditolak(): void
    {
        $nota = Purchase::create([
            'invoice_number' => 'PB-KOSONG',
            'purchased_at' => now(),
            'status' => 'draft',
        ]);

        $this->expectException(RuntimeException::class);

        $this->posting->postPurchase($nota);
    }

    // ------------------------------------------------------------- penjualan

    public function test_penjualan_ditolak_saat_stok_kurang_tanpa_meninggalkan_jejak(): void
    {
        $barang = $this->barang(['stock' => 3, 'cost_price' => 1000]);
        $dompet = $this->dompet(500_000);
        $nota = $this->penjualan($barang, qty: 5, harga: 2000, dompet: $dompet);

        try {
            $this->posting->postSale($nota);
            $this->fail('Penjualan melebihi stok seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak cukup', $e->getMessage());
        }

        // Klaim yang dijaga: nota tidak boleh setengah terbukukan.
        $this->assertSame(3.0, (float) $barang->refresh()->stock);
        $this->assertSame(500_000.0, (float) $dompet->refresh()->current_balance);
        $this->assertSame('draft', $nota->refresh()->status);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_penjualan_membekukan_harga_pokok_agar_laba_historis_tidak_berubah(): void
    {
        $barang = $this->barang(['stock' => 10, 'cost_price' => 1200]);
        $dompet = $this->dompet(0);
        $nota = $this->penjualan($barang, qty: 4, harga: 3000, dompet: $dompet);

        $this->posting->postSale($nota);

        $baris = $nota->refresh()->items()->sole();
        $this->assertSame(1200.0, (float) $baris->unit_cost);
        $this->assertSame(4_800.0, (float) $nota->total_cost);
        $this->assertSame(12_000.0, (float) $nota->total);
        $this->assertSame(7_200.0, $nota->grossProfit());
        $this->assertSame(6.0, (float) $barang->refresh()->stock);
        $this->assertSame(12_000.0, (float) $dompet->refresh()->current_balance);

        // Harga beli naik bulan depan — laba nota lama tidak boleh ikut bergerak.
        $barang->forceFill(['cost_price' => 5000])->save();

        $this->assertSame(1200.0, (float) $baris->refresh()->unit_cost);
        $this->assertSame(4_800.0, (float) $nota->refresh()->total_cost);
    }

    // ----------------------------------------------------------- pembatalan

    public function test_pembatalan_menghitung_ulang_dan_tetap_benar_walau_ada_nota_sesudahnya(): void
    {
        $barang = $this->barang();
        $pertama = $this->pembelian($barang, qty: 10, harga: 1000);
        $kedua = $this->pembelian($barang, qty: 5, harga: 1000);

        $this->posting->postPurchase($pertama);
        $this->posting->postPurchase($kedua);
        $this->assertSame(15.0, (float) $barang->refresh()->stock);

        // Membatalkan nota yang LEBIH LAMA, bukan yang terakhir.
        $this->posting->unpostPurchase($pertama);

        $this->assertSame(5.0, (float) $barang->refresh()->stock);
        $this->assertSame('draft', $pertama->refresh()->status);
        $this->assertSame('posted', $kedua->refresh()->status);
        $this->assertSame(1, StockMovement::where('item_id', $barang->id)->count());
    }

    /**
     * Pembatalan menghitung ulang stok dari kartu mutasi, bukan menerapkan
     * mutasi kebalikan. Karena itu stok di sini harus lahir dari pembelian yang
     * benar-benar dibukukan — persis seperti di aplikasi, di mana kolom stok
     * pada form barang memang dimatikan.
     */
    public function test_pembatalan_penjualan_mengembalikan_stok_dan_saldo(): void
    {
        $dompet = $this->dompet(0);
        $barang = $this->barangBerstok(qty: 10, harga: 1000, dompet: $dompet);

        $this->assertSame(10.0, (float) $barang->refresh()->stock);
        $this->assertSame(-10_000.0, (float) $dompet->refresh()->current_balance);

        $nota = $this->penjualan($barang, qty: 4, harga: 2500, dompet: $dompet);
        $this->posting->postSale($nota);
        $this->assertSame(6.0, (float) $barang->refresh()->stock);

        $this->posting->unpostSale($nota->refresh());

        $this->assertSame(10.0, (float) $barang->refresh()->stock);
        $this->assertSame(-10_000.0, (float) $dompet->refresh()->current_balance);
        $this->assertSame(0.0, (float) $nota->refresh()->total_cost);
        $this->assertSame('draft', $nota->status);
    }

    // ---------------------------------------------------------------- opname

    public function test_opname_hanya_mengoreksi_baris_yang_selisih(): void
    {
        $cocok = $this->barang(['stock' => 10, 'cost_price' => 1000]);
        $kurang = $this->barang(['stock' => 10, 'cost_price' => 1000]);
        $lebih = $this->barang(['stock' => 10, 'cost_price' => 1000]);

        $opname = StockOpname::create([
            'opname_number' => 'SO-1',
            'opname_date' => now(),
            'status' => 'draft',
        ]);

        foreach ([[$cocok, 10], [$kurang, 7], [$lebih, 13]] as [$barang, $fisik]) {
            StockOpnameItem::create([
                'stock_opname_id' => $opname->id,
                'item_id' => $barang->id,
                'system_qty' => 10,
                'physical_qty' => $fisik,
            ]);
        }

        $this->posting->postOpname($opname);

        $this->assertSame(10.0, (float) $cocok->refresh()->stock);
        $this->assertSame(7.0, (float) $kurang->refresh()->stock);
        $this->assertSame(13.0, (float) $lebih->refresh()->stock);

        // Baris yang cocok tidak boleh meninggalkan mutasi sama sekali.
        $this->assertSame(0, StockMovement::where('item_id', $cocok->id)->count());
        $this->assertSame(2, StockMovement::count());

        $this->assertSame(3.0, (float) StockMovement::where('item_id', $kurang->id)->sole()->qty_out);
        $this->assertSame(3.0, (float) StockMovement::where('item_id', $lebih->id)->sole()->qty_in);
        $this->assertSame('posted', $opname->refresh()->status);
    }

    // -------------------------------------------------------------- fixture

    private function barang(array $atribut = []): Item
    {
        return Item::create(array_merge([
            'sku' => sprintf('BRG-%03d', ++$this->urut),
            'name' => 'Barang Uji '.$this->urut,
            'unit' => 'pcs',
            'stock' => 0,
            'cost_price' => 0,
            'sell_price' => 0,
        ], $atribut));
    }

    /** Barang yang stoknya lahir dari pembelian yang dibukukan, bukan diketik. */
    private function barangBerstok(float $qty, float $harga, ?Wallet $dompet = null): Item
    {
        $barang = $this->barang();

        $this->posting->postPurchase($this->pembelian($barang, $qty, $harga, $dompet));

        return $barang->refresh();
    }

    private function dompet(float $saldoAwal): Wallet
    {
        return Wallet::create([
            'name' => 'Kas Uji '.++$this->urut,
            'type' => 'cash',
            'opening_balance' => $saldoAwal,
            'current_balance' => $saldoAwal,
        ]);
    }

    private function pembelian(Item $barang, float $qty, float $harga, ?Wallet $dompet = null): Purchase
    {
        $nota = Purchase::create([
            'invoice_number' => sprintf('PB-%03d', ++$this->urut),
            'purchased_at' => now(),
            'wallet_id' => $dompet?->id,
            'status' => 'draft',
        ]);

        PurchaseItem::create([
            'purchase_id' => $nota->id,
            'item_id' => $barang->id,
            'qty' => $qty,
            'unit_cost' => $harga,
            'subtotal' => $qty * $harga,
        ]);

        return $nota->refresh();
    }

    private function penjualan(Item $barang, float $qty, float $harga, ?Wallet $dompet = null): Sale
    {
        $nota = Sale::create([
            'invoice_number' => sprintf('PJ-%03d', ++$this->urut),
            'sold_at' => now(),
            'wallet_id' => $dompet?->id,
            'status' => 'draft',
        ]);

        SaleItem::create([
            'sale_id' => $nota->id,
            'item_id' => $barang->id,
            'qty' => $qty,
            'unit_price' => $harga,
            'subtotal' => $qty * $harga,
        ]);

        return $nota->refresh();
    }
}
