<?php

namespace Tests\Feature;

use App\Filament\Resources\Formulas\Pages\CreateFormula;
use App\Models\ExhaustComponent;
use App\Models\Formula;
use App\Models\FormulaLine;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\ProductionItem;
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
