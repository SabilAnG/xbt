<?php

namespace Tests\Feature;

use App\Filament\Pages\KalkulatorHpp;
use App\Models\ExhaustComponent;
use App\Models\Formula;
use App\Models\FormulaLine;
use App\Models\FormulaService;
use App\Models\ProductionItem;
use App\Models\ProductionService;
use App\Models\User;
use App\Support\HitungHpp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kalkulator HPP: dari resep ke harga yang perlu dipasang.
 *
 * Seluruh isinya angka uang. Yang dijaga di sini bukan tampilannya, melainkan
 * rumusnya — terutama dua hal yang paling sering dikira sama padahal tidak:
 * markup dengan margin, dan menambah potongan aplikasi dengan membaginya.
 */
class KalkulatorHppTest extends TestCase
{
    use RefreshDatabase;

    /** Bahan Rp6.000 + jasa Rp150.000 = HPP Rp156.000 per set. */
    private function resep(array $atribut = []): Formula
    {
        $formula = Formula::create(array_merge([
            'code' => 'RS-MIO', 'name' => 'Racing Standar',
        ], $atribut));

        $pipa = ProductionItem::create([
            'sku' => 'PIP-001', 'name' => 'Pipa SS 201 Ø28',
            'shape' => 'linear', 'unit' => 'batang',
            'length_mm' => 6_000, 'cost_price' => 90_000,   // Rp15 per mm
        ]);

        FormulaLine::create([
            'formula_id' => $formula->id,
            'exhaust_component_id' => ExhaustComponent::komponen()->where('name', 'P1')->sole()->id,
            'production_item_id' => $pipa->id,
            'input_mode' => 'length', 'size_unit' => 'cm',
            'piece_length_mm' => 200, 'piece_count' => 2,   // 400 mm -> Rp6.000
        ]);

        FormulaService::create([
            'formula_id' => $formula->id,
            'production_service_id' => ProductionService::create([
                'name' => 'Chrome', 'slug' => 'chrome', 'unit' => 'unit', 'rate' => 150_000,
            ])->id,
            'qty' => 1,
        ]);

        return $formula->fresh(['lines.item', 'services.service']);
    }

    public function test_hpp_menjumlah_bahan_dan_jasa_lalu_dikali_jumlah_pesanan(): void
    {
        $hitung = new HitungHpp($this->resep(), jumlah: 5);

        $this->assertSame(6_000.0, $hitung->bahanPerUnit());
        $this->assertSame(150_000.0, $hitung->jasaPerUnit());
        $this->assertSame(156_000.0, $hitung->hppPerUnit());
        $this->assertSame(780_000.0, $hitung->hppTotal());
    }

    public function test_markup_dihitung_dari_hpp(): void
    {
        $hitung = new HitungHpp($this->resep(), markupPersen: 40, markupResellerPersen: 20);

        $this->assertSame(218_400.0, $hitung->hargaJual());      // 156.000 + 40%
        $this->assertSame(187_200.0, $hitung->hargaReseller());  // 156.000 + 20%
        $this->assertSame(62_400.0, $hitung->laba($hitung->hargaJual()));
    }

    /**
     * Markup 40% hanya bermargin 28,6%. Mengira keduanya sama adalah cara
     * paling lazim mematok harga yang terlalu murah.
     */
    public function test_margin_bukan_markup(): void
    {
        $hitung = new HitungHpp($this->resep(), markupPersen: 40);

        $this->assertEqualsWithDelta(28.57, $hitung->margin($hitung->hargaJual()), 0.01);
    }

    /**
     * Potongan aplikasi diambil dari harga terpasang, jadi harganya dibagi
     * sisanya — bukan ditambah sebesar potongan itu. Menambah 10% pada harga
     * yang dipotong 10% selalu kurang.
     */
    public function test_harga_marketplace_dibagi_sisa_potongan_bukan_ditambah(): void
    {
        $hitung = new HitungHpp(
            $this->resep(),
            markupPersen: 40,
            biayaAdmin: 2_000,
            biayaOngkir: 10_000,
            potonganPersen: 10,
        );

        // (218.400 + 2.000 + 10.000) / 0,9
        $this->assertEqualsWithDelta(256_000.0, $hitung->hargaPasang(), 0.5);

        // Yang salah kaprah: 218.400 + 12.000 lalu ditambah 10% = 253.440.
        $this->assertGreaterThan(253_440.0, $hitung->hargaPasang());

        // Dan yang diterima kembali persis sebesar harga jual umum.
        $this->assertEqualsWithDelta($hitung->hargaJual(), $hitung->diterimaBersih(), 0.5);
    }

    public function test_tanpa_potongan_harga_pasang_hanya_menambah_biayanya(): void
    {
        $hitung = new HitungHpp(
            $this->resep(),
            markupPersen: 40,
            biayaAdmin: 2_000,
            potonganPersen: 0,
        );

        $this->assertSame(220_400.0, $hitung->hargaPasang());
    }

    /** Sekali resep menghasilkan tiga: modalnya dibagi tiga, bukan diulang. */
    public function test_hasil_lebih_dari_satu_membagi_hppnya(): void
    {
        $hitung = new HitungHpp($this->resep(['output_qty' => 3]));

        $this->assertSame(52_000.0, $hitung->hppPerUnit());
    }

    public function test_halaman_kalkulator_bisa_dibuka_dan_menampilkan_rinciannya(): void
    {
        $this->actingAs(User::factory()->create());

        $formula = $this->resep();

        Livewire::test(KalkulatorHpp::class)
            ->assertSuccessful()
            ->fillForm(['formula_id' => $formula->id, 'jumlah' => 2])
            ->assertSee('Chrome')
            ->assertSee('Pipa SS 201 Ø28');
    }

    public function test_halaman_tetap_terbuka_saat_belum_ada_formula(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(KalkulatorHpp::class)
            ->assertSuccessful()
            ->assertSee('Pilih formula dulu');
    }
}
