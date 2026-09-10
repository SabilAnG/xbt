<?php

namespace Tests\Feature;

use App\Models\CostComponent;
use App\Models\Formula;
use App\Models\FormulaCost;
use App\Models\FormulaMaterial;
use App\Models\Material;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kebutuhan bahan, HPP, dan ke mana sisanya pergi.
 *
 * Kesalahan di sini tidak memunculkan error — yang muncul harga jual yang
 * dihitung dari modal keliru. Jadi yang diuji angkanya, bukan jalannya.
 */
class FormulaSisaTest extends TestCase
{
    use RefreshDatabase;

    private int $urut = 0;

    // ------------------------------------------------------------ potong batang

    public function test_potong_batang_menghitung_berapa_muat_dan_sisa_ujungnya(): void
    {
        $pipa = $this->pipa();

        // Batang 6.000 mm, potongan 800 mm, mata potong 3 mm:
        // 7 potong memakan 7x800 + 6x3 = 5.618 mm, ujungnya sisa 382 mm.
        $n = $pipa->barNesting(800);

        $this->assertTrue($n['muat_utuh']);
        $this->assertSame(7, $n['muat']);
        $this->assertSame(5_618.0, $n['terpakai_per_batang']);
        $this->assertSame(382.0, $n['sisa_per_batang']);
    }

    public function test_potongan_lebih_panjang_dari_batang_tidak_bisa_dipotong(): void
    {
        $n = $this->pipa()->barNesting(7_000);

        $this->assertFalse($n['muat_utuh']);
        $this->assertSame(0, $n['muat']);
    }

    // ------------------------------------------------------- kebutuhan & HPP

    public function test_kebutuhan_bahan_dan_hpp_per_unit_untuk_sejumlah_target(): void
    {
        $pipa = $this->pipa();                       // Rp90.000/batang 6 m -> Rp15/mm
        $formula = $this->formula(hasil: 1);

        // Dua potong 800 mm tiap knalpot = 1.600 mm per resep.
        $this->barisBahan($formula, $pipa, panjang: 800, jumlah: 2);
        $this->barisJasa($formula, tarifPerJam: 25_000, menit: 20);

        $formula->refresh()->load('materials.material', 'costs.component');

        // --- kebutuhan untuk 5 pcs ---
        $butuh = $formula->requirementFor(5);

        // Yang dibebankan jatah batang, bukan panjang potongannya: satu batang
        // hanya menghasilkan 7 potong, jadi tiap potong menanggung 6.000/7 =
        // 857,14 mm. Dua potong per resep = 1.714,29 mm, dikali 5 batch.
        $this->assertSame(5.0, $butuh['batch']);
        $this->assertEqualsWithDelta(8_571.43, $butuh['baris'][0]['butuh'], 0.01);
        $this->assertSame('8,57 m', $butuh['baris'][0]['butuh_label']);
        $this->assertEqualsWithDelta(128_571.43, $butuh['biaya_bahan'], 0.01);

        // --- HPP per unit ---
        // bahan 1.714,29 x Rp15 = 25.714,29; jasa 20 menit @ 25.000/jam = 8.333,33
        $this->assertEqualsWithDelta(25_714.29, $formula->materialCost(), 0.01);
        $this->assertEqualsWithDelta(8_333.33, $formula->serviceCost(), 0.01);
        $this->assertEqualsWithDelta(0.0, $formula->overheadCost(), 0.01);
        $this->assertEqualsWithDelta(34_047.62, $formula->hppPerUnit(), 0.01);
    }

    // ------------------------------------------------------------ sisa & sampah

    /**
     * Angka yang tidak bisa diperiksa adalah angka yang tidak bisa dipercaya.
     * Tiga bagian itu harus menjumlah tepat menjadi yang dibeli.
     */
    public function test_bersih_susut_dan_sisa_menjumlah_tepat_sama_dengan_yang_dibeli(): void
    {
        $formula = $this->formula(hasil: 1);
        $this->barisBahan($formula, $this->pipa(), panjang: 800, jumlah: 2);
        $this->barisBahan($formula, $this->plat(), panjang: 324, lebar: 320, jumlah: 1);

        $hasil = $formula->refresh()->wasteFor(5);

        $this->assertCount(2, $hasil['baris']);

        foreach ($hasil['baris'] as $r) {
            $this->assertEqualsWithDelta(
                $r['dibeli'],
                $r['bersih'] + $r['susut'] + $r['sisa'],
                0.01,
                'Bahan '.$r['material']->name.' tidak berimbang.'
            );
        }
    }

    public function test_sisa_pipa_dari_pembelian_utuh_kembali_jadi_stok(): void
    {
        $formula = $this->formula(hasil: 1);
        $this->barisBahan($formula, $this->pipa(min: 300), panjang: 800, jumlah: 2);

        $r = $formula->refresh()->wasteFor(5)['baris'][0];

        // Menempel di produk 8.000 mm, ujung batang yang dibebankan 571,43 mm,
        // beli 2 batang (12.000 mm), sisanya 3.428,57 mm kembali jadi stok.
        $this->assertSame(8_000.0, $r['bersih']);
        $this->assertEqualsWithDelta(571.43, $r['susut'], 0.01);
        $this->assertSame(2.0, $r['beli']);
        $this->assertSame(12_000.0, $r['dibeli']);
        $this->assertEqualsWithDelta(3_428.57, $r['sisa'], 0.01);
        $this->assertTrue($r['sisa_berguna'], 'Sisa 3,4 m di atas batas 300 mm, seharusnya kembali jadi stok.');
        $this->assertStringContainsString('7 potong', $r['potong']);
    }

    public function test_sisa_di_bawah_batas_dihitung_sebagai_sampah(): void
    {
        // Batas dinaikkan sampai di atas sisa yang akan muncul.
        $formula = $this->formula(hasil: 1);
        $this->barisBahan($formula, $this->pipa(min: 5_000), panjang: 800, jumlah: 2);

        $hasil = $formula->refresh()->wasteFor(5);
        $r = $hasil['baris'][0];

        $this->assertEqualsWithDelta(3_428.57, $r['sisa'], 0.01);
        $this->assertFalse($r['sisa_berguna'], 'Sisa 3,4 m di bawah batas 5 m, seharusnya dihitung sampah.');
        $this->assertEqualsWithDelta(51_428.57, $hasil['rp_sisa_terbuang'], 0.01);  // 3.428,57 mm x Rp15
        $this->assertEqualsWithDelta(0.0, $hasil['rp_sisa_berguna'], 0.01);

        // Ujung batang yang dibebankan plus sisa yang dibuang: seluruh 4.000 mm
        // yang tidak menempel di produk, berapa pun cara membaginya.
        $this->assertEqualsWithDelta(8_571.43, $hasil['rp_susut'], 0.01);
        $this->assertEqualsWithDelta(60_000.0, $hasil['rp_sampah'], 0.01);
    }

    public function test_sudut_plat_yang_terbuang_masuk_hitungan_susut(): void
    {
        $formula = $this->formula(hasil: 1);
        $this->barisBahan($formula, $this->plat(), panjang: 324, lebar: 320, jumlah: 1);

        $r = $formula->refresh()->wasteFor(1)['baris'][0];

        // Lembar 1200x2400 muat 21 potong 324x320; jatah tiap potong
        // 2.880.000/21 = 137.142,86 mm2 sedangkan yang menempel 103.680 mm2.
        $this->assertSame(103_680.0, $r['bersih']);
        $this->assertEqualsWithDelta(33_462.86, $r['susut'], 0.01);
        $this->assertGreaterThan(0, $r['rp_susut']);
        $this->assertStringContainsString('21 potong', $r['potong']);
    }

    // --------------------------------------------------------------- fixture

    private function pipa(float $min = 0): Material
    {
        return Material::create([
            'sku' => sprintf('PIP-%03d', ++$this->urut),
            'name' => 'Pipa Stainless 1.5 inch',
            'dimension_type' => 'linear',
            'unit' => 'batang',
            'length_mm' => 6_000,
            'cost_price' => 90_000,     // -> Rp15 per mm
            'min_reusable' => $min,
        ]);
    }

    private function plat(float $min = 0): Material
    {
        return Material::create([
            'sku' => sprintf('PLT-%03d', ++$this->urut),
            'name' => 'Plat Stainless 0.8 mm',
            'dimension_type' => 'sheet',
            'unit' => 'lembar',
            'sheet_length_mm' => 1_200,
            'sheet_width_mm' => 2_400,
            'cost_price' => 500_000,
            'min_reusable' => $min,
        ]);
    }

    private function formula(float $hasil): Formula
    {
        return Formula::create([
            'code' => sprintf('F-%03d', ++$this->urut),
            'name' => 'Knalpot Racing Mio',
            'output_qty' => $hasil,
            'output_unit' => 'pcs',
            'is_active' => true,
        ]);
    }

    private function barisBahan(
        Formula $formula,
        Material $bahan,
        float $panjang,
        ?float $lebar = null,
        float $jumlah = 1,
    ): FormulaMaterial {
        return FormulaMaterial::create([
            'formula_id' => $formula->id,
            'material_id' => $bahan->id,
            'bom_group' => 'header',
            'input_mode' => $lebar === null ? 'length' : 'rect',
            'piece_length_mm' => $panjang,
            'piece_width_mm' => $lebar,
            'piece_count' => $jumlah,
            'waste_percent' => 0,
            'use_nesting' => $lebar !== null,
        ]);
    }

    private function barisJasa(Formula $formula, float $tarifPerJam, float $menit): FormulaCost
    {
        $proses = CostComponent::create([
            'name' => 'Las Argon',
            'slug' => 'las-argon-'.++$this->urut,
            'type' => 'tenaga_kerja',
            'rate_type' => 'per_hour',
            'unit' => 'jam',
            'rate' => $tarifPerJam,
            'default_minutes' => $menit,
        ]);

        return FormulaCost::create([
            'formula_id' => $formula->id,
            'cost_component_id' => $proses->id,
            'bom_group' => 'header',
            'qty' => 1,
            'minutes' => $menit,
        ]);
    }
}
