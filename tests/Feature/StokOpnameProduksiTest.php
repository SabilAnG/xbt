<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductionItemOpnames\Pages\CreateProductionItemOpname;
use App\Filament\Resources\ProductionItems\Schemas\ProductionItemForm;
use App\Models\ProductionItem;
use App\Models\ProductionItemMovement;
use App\Models\ProductionItemOpname;
use App\Models\ProductionItemOpnameItem;
use App\Models\User;
use App\Services\ProductionStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Stok opname barang produksi — sekaligus jalan masuk stok pertama.
 *
 * Yang dijaga di sini bukan "tidak meledak", melainkan aturan yang membuat
 * angkanya bisa dipercaya: stok selalu turunan kartu stok, tidak pernah
 * angka berdiri sendiri.
 */
class StokOpnameProduksiTest extends TestCase
{
    use RefreshDatabase;

    private ProductionStockService $stok;

    private int $urut = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stok = app(ProductionStockService::class);
    }

    public function test_opname_mengisi_stok_pertama_kali(): void
    {
        // Barang baru belum pernah punya stok: catatannya 0, hitungan fisiknya
        // langsung menjadi stok awal.
        $pipa = $this->pipa();
        $this->assertSame(0.0, (float) $pipa->stock);

        $opname = $this->sesi();
        $this->baris($opname, $pipa, sistem: 0, fisik: 42_000);

        $this->stok->postOpname($opname);

        $this->assertSame(42_000.0, (float) $pipa->refresh()->stock);
        $this->assertSame('posted', $opname->refresh()->status);

        $mutasi = ProductionItemMovement::sole();
        $this->assertSame('opname', $mutasi->type);
        $this->assertSame(42_000.0, (float) $mutasi->qty_in);
        $this->assertSame(0.0, (float) $mutasi->qty_out);
        $this->assertSame(42_000.0, (float) $mutasi->balance_after);
        $this->assertSame(15.0, (float) $mutasi->unit_cost);   // Rp90.000 / 6.000 mm
    }

    public function test_hanya_baris_yang_selisih_yang_mengoreksi_stok(): void
    {
        $cocok = $this->pipa();
        $kurang = $this->pipa();
        $lebih = $this->pipa();

        $opname = $this->sesi();
        $this->baris($opname, $cocok, sistem: 6_000, fisik: 6_000);
        $this->baris($opname, $kurang, sistem: 6_000, fisik: 5_000);
        $this->baris($opname, $lebih, sistem: 6_000, fisik: 7_000);

        // Stok awalnya nol karena kartunya kosong; yang dipakai adalah selisih.
        $this->stok->postOpname($opname);

        $this->assertSame(0.0, (float) $cocok->refresh()->stock);
        $this->assertSame(-1_000.0, (float) $kurang->refresh()->stock);
        $this->assertSame(1_000.0, (float) $lebih->refresh()->stock);

        // Baris yang cocok tidak meninggalkan jejak apa pun.
        $this->assertSame(0, ProductionItemMovement::where('production_item_id', $cocok->id)->count());
        $this->assertSame(2, ProductionItemMovement::count());
    }

    public function test_stok_selalu_sama_dengan_jumlah_kartu_stoknya(): void
    {
        $pipa = $this->pipa();

        $pertama = $this->sesi();
        $this->baris($pertama, $pipa, sistem: 0, fisik: 12_000);
        $this->stok->postOpname($pertama);

        $kedua = $this->sesi();
        $this->baris($kedua, $pipa, sistem: 12_000, fisik: 9_500);
        $this->stok->postOpname($kedua);

        $pipa->refresh();

        $this->assertSame(9_500.0, (float) $pipa->stock);
        $this->assertSame(9_500.0, $pipa->computedStock(), 'Kolom stok harus selalu cocok dengan kartu stok.');
    }

    public function test_pembatalan_menghitung_ulang_dan_tetap_benar_walau_ada_opname_sesudahnya(): void
    {
        $pipa = $this->pipa();

        $pertama = $this->sesi();
        $this->baris($pertama, $pipa, sistem: 0, fisik: 12_000);
        $this->stok->postOpname($pertama);

        $kedua = $this->sesi();
        $this->baris($kedua, $pipa, sistem: 12_000, fisik: 15_000);   // tambah 3.000
        $this->stok->postOpname($kedua);
        $this->assertSame(15_000.0, (float) $pipa->refresh()->stock);

        // Batalkan yang LEBIH LAMA, bukan yang terakhir.
        $this->stok->unpostOpname($pertama->refresh());

        // Tersisa koreksi dari nota kedua saja: +3.000.
        $this->assertSame(3_000.0, (float) $pipa->refresh()->stock);
        $this->assertSame('draft', $pertama->refresh()->status);
        $this->assertSame('posted', $kedua->refresh()->status);
        $this->assertSame(1, ProductionItemMovement::count());
    }

    public function test_opname_yang_sudah_dibukukan_tidak_bisa_dibukukan_dua_kali(): void
    {
        $pipa = $this->pipa();
        $opname = $this->sesi();
        $this->baris($opname, $pipa, sistem: 0, fisik: 6_000);

        $this->stok->postOpname($opname);

        $this->expectException(RuntimeException::class);

        try {
            $this->stok->postOpname($opname->refresh());
        } finally {
            // Yang penting bukan adanya exception, tapi stoknya tidak dobel.
            $this->assertSame(6_000.0, (float) $pipa->refresh()->stock);
            $this->assertSame(1, ProductionItemMovement::count());
        }
    }

    public function test_opname_tanpa_baris_ditolak(): void
    {
        $this->expectException(RuntimeException::class);

        $this->stok->postOpname($this->sesi());
    }

    // ------------------------------------------- mengisi master dari opname

    /**
     * Barang yang dibuat lewat stok opname harus selengkap yang dibuat lewat
     * menunya sendiri — kalau ukurannya hilang, HPP-nya tidak akan pernah benar.
     */
    public function test_isian_ringkas_memuat_ukuran_selengkap_form_aslinya(): void
    {
        $medan = array_filter(array_map(
            fn ($komponen) => method_exists($komponen, 'getName') ? $komponen->getName() : null,
            ProductionItemForm::ringkas(),
        ));

        $wajib = [
            'name', 'sku', 'production_item_category_id', 'role', 'source',
            'shape', 'size_unit', 'unit', 'cost_price',
            'length_mm', 'width_mm', 'diameter_mm', 'thickness_mm',
            'weight_gram', 'volume_ml', 'min_reusable',
        ];

        foreach ($wajib as $nama) {
            $this->assertContains($nama, $medan, "Isian ringkas kehilangan medan {$nama}.");
        }

        // Stok justru tidak boleh ada: ia hanya berubah lewat kartu stok.
        $this->assertNotContains('stock', $medan);
    }

    public function test_form_opname_menyaring_barang_lewat_jenisnya(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateProductionItemOpname::class)
            ->assertSuccessful()
            ->assertSee('Jenis Barang')
            ->assertSee('Catatan Sistem')
            ->assertSee('Hitung Fisik');
    }

    // --------------------------------------------------------------- fixture

    private function pipa(): ProductionItem
    {
        return ProductionItem::create([
            'sku' => sprintf('PIP-%03d', ++$this->urut),
            'name' => 'Pipa SS 201 Ø28 #'.$this->urut,
            'shape' => 'linear',
            'unit' => 'batang',
            'length_mm' => 6_000,
            'cost_price' => 90_000,     // -> Rp15 per mm
        ]);
    }

    private function sesi(): ProductionItemOpname
    {
        return ProductionItemOpname::create([
            'opname_number' => sprintf('SOP-%03d', ++$this->urut),
            'opname_date' => now(),
            'counted_by' => 'Budi',
        ]);
    }

    private function baris(
        ProductionItemOpname $opname,
        ProductionItem $barang,
        float $sistem,
        float $fisik,
    ): ProductionItemOpnameItem {
        return ProductionItemOpnameItem::create([
            'production_item_opname_id' => $opname->id,
            'production_item_id' => $barang->id,
            'system_qty' => $sistem,
            'physical_qty' => $fisik,
        ]);
    }
}
