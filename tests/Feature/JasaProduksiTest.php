<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductionServices\Pages\ListProductionServices;
use App\Models\ProductionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Jasa produksi: biaya membuat knalpot yang bukan bahan.
 *
 * Baru daftarnya — formula belum memanggilnya. Yang dijaga di sini cuma dua
 * hal, dan keduanya soal uang: tarifnya tersimpan apa adanya, dan perkaliannya
 * benar saat nanti dipakai menghitung modal.
 */
class JasaProduksiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_jasa_ditambah_lewat_modal_berikut_tarifnya(): void
    {
        Livewire::test(ListProductionServices::class)
            ->callAction('create', [
                'name' => 'Chrome',
                'slug' => 'chrome',
                'unit' => 'unit',
                'rate' => 150_000,
            ])
            ->assertHasNoActionErrors();

        $chrome = ProductionService::where('slug', 'chrome')->sole();

        $this->assertSame(150_000.0, (float) $chrome->rate);
        $this->assertSame('unit', $chrome->unit);
        $this->assertTrue($chrome->is_active);
    }

    /**
     * Satuannya bukan hiasan: las ditagih per titik, bukan per unit, dan
     * menyamakan keduanya membuat modal satu knalpot meleset sepuluh kali.
     */
    public function test_tarif_dikalikan_banyaknya_satuan(): void
    {
        $las = ProductionService::create([
            'name' => 'Las Argon',
            'slug' => 'las-argon',
            'unit' => 'titik',
            'rate' => 5_000,
        ]);

        $this->assertSame(60_000.0, $las->costFor(12));
        $this->assertSame(0.0, $las->costFor(0));
        $this->assertSame('Rp5.000 / titik', $las->displayRate());
    }

    public function test_daftar_memuat_jasa_berikut_tarifnya(): void
    {
        ProductionService::create([
            'name' => 'Poles',
            'slug' => 'poles',
            'unit' => 'set',
            'rate' => 75_000,
        ]);

        Livewire::test(ListProductionServices::class)
            ->assertSuccessful()
            ->assertSee('Poles')
            ->assertSee('per set');
    }

    /**
     * Belum ada yang memakainya, jadi menghapusnya tidak boleh dihalangi —
     * penjagaan itu baru berlaku begitu jasa masuk ke formula.
     */
    public function test_jasa_yang_belum_dipakai_bisa_dihapus(): void
    {
        $bending = ProductionService::create([
            'name' => 'Bending',
            'slug' => 'bending',
            'unit' => 'titik',
            'rate' => 10_000,
        ]);

        Livewire::test(ListProductionServices::class)
            ->callTableAction('delete', $bending)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('production_services', ['slug' => 'bending']);
    }
}
