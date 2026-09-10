<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductionItems\Pages\CreateProductionItem;
use App\Models\ProductionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Master barang produksi — fondasi seluruh perhitungan modal.
 *
 * Konversi satuan beli ke satuan pakai adalah tempat kesalahan paling mahal:
 * ia tidak memunculkan error, hanya harga pokok yang salah lalu dipercaya.
 * Karena itu yang diuji angkanya.
 */
class BarangProduksiTest extends TestCase
{
    use RefreshDatabase;

    private int $urut = 0;

    // ------------------------------------------------------- sumber & peran

    public function test_barang_baru_bawaannya_dibeli_dan_bahan_utama(): void
    {
        $barang = $this->pipa();

        $this->assertSame('beli', $barang->refresh()->source);
        $this->assertSame('utama', $barang->role);
        $this->assertTrue($barang->bisaDibeli());
        $this->assertFalse($barang->bisaDiproduksi());
    }

    public function test_barang_yang_bisa_dua_duanya_tidak_memaksa_salah_satu(): void
    {
        $keduanya = $this->pipa(['source' => 'beli_produksi']);

        $this->assertTrue($keduanya->bisaDibeli());
        $this->assertTrue($keduanya->bisaDiproduksi());
        $this->assertSame('Dibeli atau dibuat sendiri', $keduanya->displaySource());
    }

    // ----------------------------------------------------------- konversi

    public function test_batangan_dikonversi_dari_satuan_beli_ke_satuan_pakai(): void
    {
        $pipa = $this->pipa();     // 1 batang 6.000 mm seharga Rp90.000

        $this->assertSame('mm', $pipa->baseUnit());
        $this->assertSame(6_000.0, $pipa->basePerUnit());
        $this->assertSame(15.0, $pipa->basePrice());
        $this->assertSame(42_000.0, $pipa->toBase(7));
        $this->assertSame(2.0, $pipa->toPurchase(12_000));
        $this->assertSame('1 batang = 6 m', $pipa->conversionLabel());
    }

    public function test_lembaran_dikonversi_memakai_luasnya(): void
    {
        $plat = $this->plat();     // 1.200 x 2.400 mm seharga Rp500.000

        $this->assertSame('mm²', $plat->baseUnit());
        $this->assertSame(2_880_000.0, $plat->basePerUnit());
        $this->assertEqualsWithDelta(0.1736, $plat->basePrice(), 0.0001);
        $this->assertSame('1 lembar = 2,88 m²', $plat->conversionLabel());
    }

    /** Ukuran yang belum diisi tidak boleh membuat pembagian nol. */
    public function test_barang_berdimensi_tanpa_ukuran_diperlakukan_satu_banding_satu(): void
    {
        $belum = ProductionItem::create([
            'sku' => 'BELUM-'.++$this->urut,
            'name' => 'Pipa belum diukur',
            'shape' => 'linear',
            'unit' => 'batang',
            'cost_price' => 90_000,
        ]);

        $this->assertSame(1.0, $belum->basePerUnit());
        $this->assertSame(90_000.0, $belum->basePrice());
    }

    // ------------------------------------------------------------ memotong

    public function test_potong_batang_menghitung_muat_dan_sisa_ujungnya(): void
    {
        // Batang 6.000 mm, potongan 800 mm, mata potong 3 mm:
        // 7 potong memakan 7x800 + 6x3 = 5.618 mm, ujungnya sisa 382 mm.
        $n = $this->pipa()->barNesting(800);

        $this->assertTrue($n['muat_utuh']);
        $this->assertSame(7, $n['muat']);
        $this->assertSame(5_618.0, $n['terpakai_per_unit']);
        $this->assertSame(382.0, $n['sisa_per_unit']);
    }

    public function test_potongan_lebih_panjang_dari_batang_tidak_bisa_dipotong(): void
    {
        $n = $this->pipa()->barNesting(7_000);

        $this->assertFalse($n['muat_utuh']);
        $this->assertSame(0, $n['muat']);
    }

    public function test_potong_lembaran_mencoba_dua_orientasi(): void
    {
        $n = $this->plat()->sheetNesting(324, 320);

        $this->assertTrue($n['muat_utuh']);
        $this->assertSame(21, $n['muat']);
        $this->assertGreaterThan(0, $n['sisa_persen']);
    }

    // ---------------------------------------------------------- sisa/sampah

    public function test_batas_sisa_memisahkan_stok_dari_sampah(): void
    {
        $berbatas = $this->pipa(['min_reusable' => 300]);

        $this->assertTrue($berbatas->isReusable(4_000));
        $this->assertFalse($berbatas->isReusable(80));

        // Batas nol berarti belum diputuskan — jangan diam-diam menyebutnya sampah.
        $tanpaBatas = $this->pipa();
        $this->assertTrue($tanpaBatas->isReusable(80));
        $this->assertFalse($tanpaBatas->isReusable(0));
    }

    // ------------------------------------------------------------- tampilan

    public function test_angka_besar_ditampilkan_dalam_satuan_yang_enak_dibaca(): void
    {
        $this->assertSame('41,84 m', $this->pipa()->formatBase(41_835));
        $this->assertSame('2,88 m²', $this->plat()->formatBase(2_880_000));
    }

    public function test_form_menampilkan_sumber_peran_dan_batas_sisa(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateProductionItem::class)
            ->assertSuccessful()
            ->assertSee('Didapat dari')
            ->assertSee('Perannya di produk')
            ->assertSee('Dibeli atau dibuat sendiri')
            ->assertSee('Sisa terkecil yang masih terpakai');
    }

    // --------------------------------------------------------------- fixture

    private function pipa(array $atribut = []): ProductionItem
    {
        return ProductionItem::create(array_merge([
            'sku' => sprintf('PIP-%03d', ++$this->urut),
            'name' => 'Pipa SS 201 Ø28',
            'shape' => 'linear',
            'unit' => 'batang',
            'length_mm' => 6_000,
            'diameter_mm' => 28,
            'cost_price' => 90_000,
        ], $atribut));
    }

    private function plat(array $atribut = []): ProductionItem
    {
        return ProductionItem::create(array_merge([
            'sku' => sprintf('PLT-%03d', ++$this->urut),
            'name' => 'Plat SS 304 0,8mm',
            'shape' => 'sheet',
            'unit' => 'lembar',
            'length_mm' => 1_200,
            'width_mm' => 2_400,
            'cost_price' => 500_000,
        ], $atribut));
    }
}
