<?php

namespace Tests\Feature;

use App\Filament\Resources\Formulas\Pages\CreateFormula;
use App\Filament\Resources\Formulas\Schemas\FormulaForm;
use App\Filament\Resources\ProductionServices\Pages\ListProductionServices;
use App\Models\ExhaustComponent;
use App\Models\Formula;
use App\Models\FormulaLine;
use App\Models\FormulaService;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\ProductionItem;
use App\Models\ProductionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Formula: resep satu knalpot untuk satu type motor.
 *
 * Kebutuhan bahan diturunkan dari ukuran yang diketik dalam satuan bengkel —
 * "20 cm x 2 potong" — dan salah di sini tidak memunculkan error, hanya modal
 * yang keliru lalu dipakai menetapkan harga jual.
 */
class FormulaTest extends TestCase
{
    use RefreshDatabase;

    private int $urut = 0;

    // ------------------------------------------------------- kebutuhan bahan

    public function test_potongan_pipa_diisi_dalam_cm_dan_dihitung_dalam_mm(): void
    {
        $pipa = $this->pipa();
        $formula = $this->formula();

        // P1: dua potong masing-masing 20 cm.
        $baris = $this->baris($formula, 'P1', $pipa, [
            'input_mode' => 'length',
            'size_unit' => 'cm',
            'piece_length_mm' => ProductionItem::toMm(20, 'cm'),
            'piece_count' => 2,
        ]);

        $this->assertSame(400.0, (float) $baris->refresh()->qty);   // 200 mm x 2

        // Kebutuhan memakai konvensi tampilan seluruh aplikasi — di bawah satu
        // meter ditulis mm. Ukuran yang diketik tetap terbaca apa adanya.
        $this->assertSame('400 mm', $baris->displayQty());
        $this->assertSame('20 cm x 2', $baris->inputLabel());

        // 400 mm x Rp15/mm
        $this->assertSame(6_000.0, $baris->subtotal());
    }

    public function test_potongan_plat_dihitung_dari_panjang_kali_lebar(): void
    {
        $plat = $this->plat();
        $formula = $this->formula();

        // Tabung silincer: satu potongan 32 x 30 cm.
        $baris = $this->baris($formula, 'Tabung Silincer', $plat, [
            'input_mode' => 'rect',
            'size_unit' => 'cm',
            'piece_length_mm' => ProductionItem::toMm(32, 'cm'),
            'piece_width_mm' => ProductionItem::toMm(30, 'cm'),
            'piece_count' => 1,
        ]);

        // 320 mm x 300 mm = 96.000 mm²
        $this->assertSame(96_000.0, (float) $baris->refresh()->qty);
        $this->assertSame('32 x 30 cm', $baris->inputLabel());
    }

    /** Baut dan pureng cukup jumlahnya — tidak ada ukuran yang perlu disebut. */
    public function test_barang_satuan_cukup_jumlahnya(): void
    {
        $pureng = ProductionItem::create([
            'sku' => 'PURENG-01', 'name' => 'Pureng Beli', 'shape' => 'count',
            'unit' => 'pcs', 'cost_price' => 25_000,
        ]);

        $baris = $this->baris($this->formula(), 'Pureng', $pureng, [
            'input_mode' => 'count',
            'piece_count' => 2,
        ]);

        $this->assertSame(2.0, (float) $baris->refresh()->qty);
        $this->assertSame(50_000.0, $baris->subtotal());
        $this->assertSame('2 pcs', $baris->inputLabel());
    }

    // ------------------------------------------------------------ modal resep

    public function test_modal_bahan_dijumlah_dari_seluruh_barisnya(): void
    {
        $formula = $this->formula();

        $this->baris($formula, 'P1', $this->pipa(), [
            'input_mode' => 'length', 'size_unit' => 'cm',
            'piece_length_mm' => ProductionItem::toMm(20, 'cm'), 'piece_count' => 2,
        ]);

        $this->baris($formula, 'P2', $this->pipa(), [
            'input_mode' => 'length', 'size_unit' => 'cm',
            'piece_length_mm' => ProductionItem::toMm(30, 'cm'), 'piece_count' => 1,
        ]);

        $formula->refresh()->load('lines.item');

        // 400 mm + 300 mm = 700 mm x Rp15
        $this->assertSame(10_500.0, $formula->materialCost());
        $this->assertSame(10_500.0, $formula->materialCostPerUnit());   // hasil 1 set
    }

    public function test_hasil_lebih_dari_satu_membagi_modalnya(): void
    {
        $formula = $this->formula(['output_qty' => 2]);

        $this->baris($formula, 'P1', $this->pipa(), [
            'input_mode' => 'length', 'size_unit' => 'cm',
            'piece_length_mm' => ProductionItem::toMm(20, 'cm'), 'piece_count' => 2,
        ]);

        $formula->refresh()->load('lines.item');

        $this->assertSame(6_000.0, $formula->materialCost());
        $this->assertSame(3_000.0, $formula->materialCostPerUnit());
    }

    // --------------------------------------------------------------- kesiapan

    public function test_baris_tanpa_bahan_membuat_formula_belum_siap(): void
    {
        $formula = $this->formula();

        FormulaLine::create([
            'formula_id' => $formula->id,
            'exhaust_component_id' => $this->komponen('P1')->id,
        ]);

        $formula->refresh()->load('lines');

        $this->assertCount(1, $formula->lineTanpaBahan());
        $this->assertFalse($formula->siap());

        $formula->lines->first()->update(['production_item_id' => $this->pipa()->id]);

        $this->assertTrue($formula->refresh()->load('lines')->siap());
    }

    public function test_nama_menyebut_type_motornya(): void
    {
        $merek = MotorcycleBrand::create(['name' => 'Yamaha', 'slug' => 'yamaha']);
        $motor = MotorcycleModel::create([
            'motorcycle_brand_id' => $merek->id, 'name' => 'Mio', 'slug' => 'mio',
        ]);

        $formula = $this->formula(['name' => 'Racing Standar', 'motorcycle_model_id' => $motor->id]);

        $this->assertSame('Racing Standar — Yamaha Mio', $formula->fullName());
    }

    // ------------------------------------------------------------- biaya jasa

    /**
     * Modal sesungguhnya bahan ditambah jasa. Menghitungnya dari bahan saja
     * membuat knalpot yang dichrome terlihat semurah yang tidak — dan selisih
     * itu berakhir di harga jual.
     */
    public function test_modal_total_menjumlah_bahan_dan_jasa(): void
    {
        $formula = $this->formula();
        $pipa = $this->pipa();

        // 20 cm x 2 = 400 mm, Rp15/mm -> Rp6.000
        $this->baris($formula, 'P1', $pipa, [
            'input_mode' => 'length', 'size_unit' => 'cm',
            'piece_length_mm' => 200, 'piece_count' => 2,
        ]);

        $this->jasa($formula, $this->chrome(), qty: 1);          // Rp150.000
        $this->jasa($formula, $this->las(), qty: 12);            // 12 x Rp5.000

        $formula->refresh();

        $this->assertSame(6_000.0, $formula->materialCost());
        $this->assertSame(210_000.0, $formula->serviceCost());
        $this->assertSame(216_000.0, $formula->totalCost());
    }

    public function test_hasil_lebih_dari_satu_membagi_jasanya_juga(): void
    {
        $formula = $this->formula(['output_qty' => 3]);
        $this->jasa($formula, $this->chrome(), qty: 3);

        $formula->refresh();

        $this->assertSame(450_000.0, $formula->serviceCost());
        $this->assertSame(150_000.0, $formula->serviceCostPerUnit());
        $this->assertSame(150_000.0, $formula->totalCostPerUnit());
    }

    /** Formula tanpa jasa tetap sah — tidak semua knalpot dichrome. */
    public function test_formula_tanpa_jasa_modalnya_bahan_saja(): void
    {
        $formula = $this->formula();
        $this->baris($formula, 'P1', $this->pipa(), [
            'input_mode' => 'length', 'size_unit' => 'cm',
            'piece_length_mm' => 200, 'piece_count' => 2,
        ]);

        $formula->refresh();

        $this->assertSame(0.0, $formula->serviceCost());
        $this->assertSame($formula->materialCost(), $formula->totalCost());
    }

    /**
     * Tarif yang sudah dipakai resep tidak boleh hilang diam-diam: modal
     * formula ikut turun tanpa jejak, dan turunnya baru ketahuan setelah harga
     * jual terlanjur ditetapkan.
     */
    public function test_jasa_yang_dipakai_formula_tidak_bisa_dihapus(): void
    {
        $this->actingAs(User::factory()->create());

        $chrome = $this->chrome();
        $this->jasa($this->formula(), $chrome, qty: 1);

        Livewire::test(ListProductionServices::class)
            ->callTableAction('delete', $chrome);

        $this->assertDatabaseHas('production_services', ['id' => $chrome->id]);
    }

    // ------------------------------------------------------------- tampilan

    /**
     * Formula baru dimulai dengan seluruh komponen, bukan halaman kosong:
     * membuat knalpot memang dimulai dari menentukan bagiannya.
     */
    public function test_formula_baru_terisi_seluruh_komponen(): void
    {
        $this->actingAs(User::factory()->create());

        $jumlahKomponen = ExhaustComponent::komponen()->where('is_active', true)->count();
        $this->assertGreaterThan(0, $jumlahKomponen);

        Livewire::test(CreateFormula::class)
            ->assertSuccessful()
            ->assertSee('P1')
            ->assertSee('Tabung Silincer')
            ->assertSee('Type Motor');
    }

    /**
     * Cara mengisi ukuran tidak lagi ditanyakan ke orang — bentuk bahannya di
     * master sudah menjawabnya. Salah menebaknya di sini berarti kolom ukuran
     * yang salah yang muncul, dan kebutuhan bahan ikut keliru.
     */
    public function test_memilih_bahan_menentukan_sendiri_cara_ukurannya(): void
    {
        $this->actingAs(User::factory()->create());

        $form = Livewire::test(CreateFormula::class)->assertSuccessful();

        [$satu, $dua, $tiga] = array_slice(array_keys($form->get('data.lines')), 0, 3);

        $form->set("data.lines.{$satu}.production_item_id", $this->pipa()->id)
            ->set("data.lines.{$dua}.production_item_id", $this->plat()->id)
            ->set("data.lines.{$tiga}.production_item_id", $this->barangBeli()->id);

        $this->assertSame('length', $form->get("data.lines.{$satu}.input_mode"));
        $this->assertSame('rect', $form->get("data.lines.{$dua}.input_mode"));
        $this->assertSame('count', $form->get("data.lines.{$tiga}.input_mode"));
    }

    /**
     * Header dan Silincer terbaca menyambung dalam satu tabel; pembatasnya
     * hanya boleh muncul di baris tempat bagiannya benar-benar berganti.
     */
    public function test_pembatas_hanya_di_baris_tempat_bagian_berganti(): void
    {
        $this->assertSame(
            [true, false, false, true, false],
            FormulaForm::awalBagian([1, 1, 1, 2, 2]),
        );

        // Komponen Header yang diselipkan di tengah Silincer memang berganti
        // bagian dua kali, dan dua-duanya perlu ditandai.
        $this->assertSame(
            [true, true, true],
            FormulaForm::awalBagian([1, 2, 1]),
        );

        // Komponen lepas tanpa bagian tetap terhitung sebagai awal.
        $this->assertSame([true, false], FormulaForm::awalBagian([null, null]));
        $this->assertSame([], FormulaForm::awalBagian([]));
    }

    // --------------------------------------------------------------- fixture

    private function formula(array $atribut = []): Formula
    {
        return Formula::create(array_merge([
            'code' => sprintf('F-%03d', ++$this->urut),
            'name' => 'Racing Standar',
        ], $atribut));
    }

    private function komponen(string $nama): ExhaustComponent
    {
        return ExhaustComponent::where('name', $nama)->sole();
    }

    private function baris(Formula $formula, string $komponen, ProductionItem $bahan, array $ukuran): FormulaLine
    {
        return FormulaLine::create(array_merge([
            'formula_id' => $formula->id,
            'exhaust_component_id' => $this->komponen($komponen)->id,
            'production_item_id' => $bahan->id,
        ], $ukuran));
    }

    private function jasa(Formula $formula, ProductionService $jasa, float $qty): FormulaService
    {
        return FormulaService::create([
            'formula_id' => $formula->id,
            'production_service_id' => $jasa->id,
            'qty' => $qty,
        ]);
    }

    private function chrome(): ProductionService
    {
        return ProductionService::create([
            'name' => 'Chrome', 'slug' => 'chrome',
            'unit' => 'unit', 'rate' => 150_000,
        ]);
    }

    private function las(): ProductionService
    {
        return ProductionService::create([
            'name' => 'Las Argon', 'slug' => 'las-argon',
            'unit' => 'titik', 'rate' => 5_000,
        ]);
    }

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

    /** Pureng, plenger, baut — dibeli jadi, jadi cukup dihitung per buah. */
    private function barangBeli(): ProductionItem
    {
        return ProductionItem::create([
            'sku' => sprintf('BLI-%03d', ++$this->urut),
            'name' => 'Pureng #'.$this->urut,
            'shape' => 'count',
            'unit' => 'pcs',
            'cost_price' => 10_000,
        ]);
    }

    private function plat(): ProductionItem
    {
        return ProductionItem::create([
            'sku' => sprintf('PLT-%03d', ++$this->urut),
            'name' => 'Plat SS 304 0,8mm #'.$this->urut,
            'shape' => 'sheet',
            'unit' => 'lembar',
            'length_mm' => 1_200,
            'width_mm' => 2_400,
            'cost_price' => 500_000,
        ]);
    }
}
