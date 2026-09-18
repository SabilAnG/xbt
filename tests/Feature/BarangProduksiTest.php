<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductionItems\Pages\ListProductionItems;
use App\Models\ProductionItem;
use App\Models\ProductionItemCategory;
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

    // ------------------------------------------------------- satuan ukuran

    public function test_ukuran_dikonversi_dari_satuan_yang_dipilih(): void
    {
        $this->assertSame(6_000.0, ProductionItem::toMm(6, 'm'));
        $this->assertSame(600.0, ProductionItem::toMm(60, 'cm'));
        $this->assertSame(28.0, ProductionItem::toMm(28, 'mm'));

        // Inch memang tidak bulat dalam biner — 1,5 x 25,4 = 38,09999...
        // Kolomnya desimal, jadi yang tersimpan tetap 38,1; di sini cukup
        // dipastikan hitungannya benar, bukan representasi floatnya.
        $this->assertEqualsWithDelta(38.1, ProductionItem::toMm(1.5, 'inch'), 0.0001);

        // Bolak-balik harus kembali ke angka semula.
        $this->assertEqualsWithDelta(1.5, ProductionItem::fromMm(38.1, 'inch'), 0.0001);
        $this->assertSame(6.0, ProductionItem::fromMm(6_000, 'm'));
    }

    /** Satuan yang tidak dikenal tidak boleh diam-diam mengubah angka. */
    public function test_satuan_kosong_diperlakukan_sebagai_milimeter(): void
    {
        $this->assertSame(500.0, ProductionItem::toMm(500, null));
        $this->assertSame(500.0, ProductionItem::fromMm(500, 'entah'));
    }

    public function test_mengetik_ukuran_dalam_meter_tersimpan_sebagai_milimeter(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListProductionItems::class)
            ->callAction('create', [
                'name' => 'Pipa 6 meter',
                'sku' => 'PIP-METER',
                'shape' => 'linear',
                'unit' => 'batang',
                'size_unit' => 'm',
                'length_mm' => 6,
                'cost_price' => 90_000,
            ])
            ->assertHasNoActionErrors();

        $pipa = ProductionItem::where('sku', 'PIP-METER')->sole();

        // Diketik 6 meter, tersimpan 6.000 mm — dan harga per mm ikut benar.
        $this->assertSame(6_000.0, (float) $pipa->length_mm);
        $this->assertSame('m', $pipa->size_unit);
        $this->assertSame(15.0, $pipa->basePrice());
    }

    public function test_mengetik_diameter_dalam_inch_tersimpan_sebagai_milimeter(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListProductionItems::class)
            ->callAction('create', [
                'name' => 'Pipa 1,5 inch',
                'sku' => 'PIP-INCH',
                'shape' => 'linear',
                'unit' => 'batang',
                'size_unit' => 'inch',
                'length_mm' => 236.22,   // ~6 m dalam inch
                'diameter_mm' => 1.5,
                'cost_price' => 90_000,
            ])
            ->assertHasNoActionErrors();

        $pipa = ProductionItem::where('sku', 'PIP-INCH')->sole();

        $this->assertSame(38.1, (float) $pipa->diameter_mm);
        $this->assertEqualsWithDelta(6_000.0, (float) $pipa->length_mm, 1.0);
    }

    /**
     * Baut dan emblem tidak punya diameter yang perlu dicatat. Menanyakannya
     * hanya menambah isian yang dilewati orang, dan isian yang dilewati
     * lama-lama membuat form terasa boleh diabaikan.
     */
    public function test_barang_satuan_bisa_dibuat_tanpa_ukuran_sama_sekali(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListProductionItems::class)
            ->callAction('create', [
                'name' => 'Baut M8 x 20',
                'sku' => 'BAUT-M8',
                'shape' => 'count',
                'unit' => 'pcs',
                'cost_price' => 1_500,
            ])
            ->assertHasNoActionErrors();

        $baut = ProductionItem::where('sku', 'BAUT-M8')->sole();

        $this->assertSame('—', $baut->displayDimensions());
        $this->assertSame(1.0, $baut->basePerUnit());
        $this->assertSame(1_500.0, $baut->basePrice());
        $this->assertSame('1 pcs', $baut->conversionLabel());
    }

    /** Yang mengetik 1,5" tidak mengenali "Ø38,1". */
    public function test_ukuran_ditampilkan_dalam_satuan_yang_dipakai_mengetiknya(): void
    {
        $inch = $this->pipa(['size_unit' => 'inch', 'diameter_mm' => 38.1]);
        $this->assertStringContainsString('Ø1,5"', $inch->displayDimensions());

        $mm = $this->pipa(['size_unit' => 'mm', 'diameter_mm' => 28]);
        $this->assertStringContainsString('Ø28mm', $mm->displayDimensions());
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

    // ----------------------------------------------- warisan dari jenis barang

    /**
     * Inti alur isiannya: jenis dijawab sekali, barang tinggal ukuran dan harga.
     */
    public function test_memilih_jenis_barang_mengisi_peran_dan_sumbernya(): void
    {
        $this->actingAs(User::factory()->create());

        $jenis = ProductionItemCategory::create([
            'name' => 'Pegas & Mounting',
            'slug' => 'pegas-mounting',
            'role' => 'aksesoris_utama',
            'source' => 'beli_produksi',
        ]);

        Livewire::test(ListProductionItems::class)
            ->mountAction('create')
            ->fillForm(['production_item_category_id' => $jenis->id])
            ->assertActionDataSet([
                'role' => 'aksesoris_utama',
                'source' => 'beli_produksi',
            ]);
    }

    public function test_aksesoris_utama_ikut_dihitung_sebagai_bahan_wajib(): void
    {
        // Pegas bentuknya aksesoris, tapi tanpa itu knalpot tidak bisa dipasang.
        $pegas = $this->pipa(['role' => 'aksesoris_utama']);
        $amplas = $this->pipa(['role' => 'penolong']);
        $pipa = $this->pipa();

        $this->assertTrue($pipa->wajib());
        $this->assertTrue($pegas->wajib(), 'Aksesoris Utama seharusnya termasuk bahan wajib.');
        $this->assertFalse($amplas->wajib());
        $this->assertSame('Aksesoris Utama', $pegas->displayRole());
    }

    public function test_form_menampilkan_sumber_peran_dan_batas_sisa(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListProductionItems::class)
            ->mountAction('create')
            ->assertMountedActionModalSee([
                'Didapat dari',
                'Perannya di produk',
                'Dibeli atau dibuat sendiri',
                'Sisa terkecil yang masih terpakai',
            ]);
    }

    // ------------------------------------------------------------- bahan

    public function test_bahan_tersimpan_terpisah_dari_nama_barang(): void
    {
        $pipa = $this->pipa(['material' => 'ss201']);

        $this->assertSame('ss201', $pipa->refresh()->material);
        $this->assertSame('Stainless SS201', $pipa->displayMaterial());
    }

    /**
     * Barang lama tidak punya jawabannya, dan menebak bahannya lebih buruk
     * daripada mengakui belum tahu — pipa besi yang terlanjur tercatat
     * stainless salah harga, bukan sekadar salah label.
     */
    public function test_bahan_yang_belum_dicatat_tidak_ditebak(): void
    {
        $this->assertNull($this->pipa()->material);
        $this->assertSame('—', $this->pipa()->displayMaterial());
    }

    /**
     * Baut stainless dan baut besi beda harga dan beda peruntukan, dan
     * keduanya barang satuan — bentuk yang ukurannya baru muncul setelah
     * diminta. Keduanya diuji sekaligus karena di situlah baut dicatat.
     */
    public function test_baut_bisa_mencatat_ukuran_dan_bahannya(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListProductionItems::class)
            ->callAction('create', [
                'name' => 'Baut M8 x 20 SS',
                'sku' => 'BAUT-M8-SS',
                'shape' => 'count',
                'material' => 'ss304',
                'unit' => 'pcs',
                'cost_price' => 2_500,
                'pakai_ukuran' => true,
                'size_unit' => 'mm',
                'diameter_mm' => 8,
                'length_mm' => 20,
            ])
            ->assertHasNoActionErrors();

        $baut = ProductionItem::where('sku', 'BAUT-M8-SS')->sole();

        $this->assertSame('Stainless SS304', $baut->displayMaterial());
        $this->assertSame(8.0, (float) $baut->diameter_mm);
        $this->assertSame(20.0, (float) $baut->length_mm);

        // Ukuran baut tetap keterangan: satuan belinya masih pcs, jadi
        // harga per satuan pakai tidak boleh ikut terbagi panjangnya.
        $this->assertSame(1.0, $baut->basePerUnit());
        $this->assertSame(2_500.0, $baut->basePrice());
    }

    public function test_form_menampilkan_isian_jenis_bahan(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListProductionItems::class)
            ->mountAction('create')
            ->assertMountedActionModalSee([
                'Jenis Bahan',
                'Stainless SS201',
                'Besi galvanis',
            ]);
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
