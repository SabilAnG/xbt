<?php

namespace Tests\Feature;

use App\Filament\Resources\ExhaustComponents\Pages\IsiBagian;
use App\Filament\Resources\ExhaustComponents\Pages\ListExhaustComponents;
use App\Models\ExhaustComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Komponen knalpot: bagian penyusun, dan bahan baku tiap bagiannya.
 */
class KomponenKnalpotTest extends TestCase
{
    use RefreshDatabase;

    public function test_susunan_awal_terisi_seperti_yang_dipakai_bengkel(): void
    {
        $bagian = ExhaustComponent::bagian()->orderBy('sort_order')->pluck('name')->all();

        $this->assertSame(['Header', 'Silincer'], $bagian);

        $header = ExhaustComponent::where('name', 'Header')->sole();
        $this->assertSame(
            ['Pureng', 'Plenger', 'P1', 'P2', 'P3', 'P4', 'Shock Pipa', 'Gantungan Per'],
            $header->children->pluck('name')->all(),
        );

        $silincer = ExhaustComponent::where('name', 'Silincer')->sole();
        $this->assertSame(
            ['Shock', 'Tutup DB', 'Tabung Silincer', 'Braket Atas', 'Braket Tengah', 'Braket Bawah'],
            $silincer->children->pluck('name')->all(),
        );
    }

    public function test_bagian_induk_dibedakan_dari_komponennya(): void
    {
        $header = ExhaustComponent::where('name', 'Header')->sole();
        $p1 = ExhaustComponent::where('name', 'P1')->sole();

        $this->assertTrue($header->isBagian());
        $this->assertFalse($p1->isBagian());

        $this->assertSame('Header', $header->fullName());
        $this->assertSame('Header / P1', $p1->fullName());
    }

    /**
     * Komponen adalah master murni: apa saja bagian penyusunnya. Bahan dan
     * ukurannya berbeda tiap model motor, jadi tempatnya di formula — bukan di
     * sini, yang kalau diisi harus digandakan per model.
     */
    public function test_komponen_tidak_menyimpan_bahan_baku(): void
    {
        $p1 = ExhaustComponent::where('name', 'P1')->sole();

        $this->assertArrayNotHasKey('production_item_id', $p1->getAttributes());
        $this->assertNotContains('production_item_id', $p1->getFillable());
    }

    public function test_komponen_baru_bisa_ditambahkan_ke_bagian_yang_ada(): void
    {
        $silincer = ExhaustComponent::where('name', 'Silincer')->sole();

        ExhaustComponent::create([
            'parent_id' => $silincer->id,
            'name' => 'Peredam Glasswool',
            'sort_order' => 70,
        ]);

        $this->assertSame(7, $silincer->refresh()->children()->count());
        $this->assertSame('Silincer / Peredam Glasswool', ExhaustComponent::where('name', 'Peredam Glasswool')->sole()->fullName());
    }

    /**
     * Daftar utama memuat bagiannya saja — enam belas komponen sekaligus
     * membuat orang membaca daftar, bukan memahami susunannya.
     */
    public function test_daftar_utama_hanya_memuat_bagiannya(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListExhaustComponents::class)
            ->assertSuccessful()
            ->assertSee('Header')
            ->assertSee('Silincer')
            ->assertDontSee('Tabung Silincer');
    }

    public function test_isi_bagian_memuat_komponennya_saja(): void
    {
        $this->actingAs(User::factory()->create());

        $header = ExhaustComponent::where('name', 'Header')->sole();
        $silincer = ExhaustComponent::where('name', 'Silincer')->sole();

        Livewire::test(IsiBagian::class, ['record' => $header])
            ->assertSuccessful()
            ->assertSee('P1')
            ->assertSee('Gantungan Per')
            ->assertDontSee('Tabung Silincer');

        Livewire::test(IsiBagian::class, ['record' => $silincer])
            ->assertSuccessful()
            ->assertSee('Tabung Silincer')
            ->assertDontSee('Gantungan Per');
    }
}
