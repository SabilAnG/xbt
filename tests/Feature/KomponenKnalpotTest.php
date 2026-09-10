<?php

namespace Tests\Feature;

use App\Filament\Resources\ExhaustComponents\Pages\IsiBagian;
use App\Filament\Resources\ExhaustComponents\Pages\ListExhaustComponents;
use App\Models\ExhaustComponent;
use App\Models\ProductionItem;
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
     * Bagian induk memang tidak dibuat dari apa pun. Membedakannya dari
     * komponen yang bahannya belum diisi itu penting — yang kedua berarti ada
     * pekerjaan yang belum selesai.
     */
    public function test_bagian_tanpa_bahan_berbeda_artinya_dari_komponen_yang_belum_diisi(): void
    {
        $header = ExhaustComponent::where('name', 'Header')->sole();
        $p1 = ExhaustComponent::where('name', 'P1')->sole();

        $this->assertSame('—', $header->displayMaterial());
        $this->assertSame('Belum dipilih', $p1->displayMaterial());

        // Hanya komponen yang terhitung belum selesai, bukan bagian induknya.
        $belum = ExhaustComponent::tanpaBahan()->pluck('name');
        $this->assertContains('P1', $belum);
        $this->assertNotContains('Header', $belum);
    }

    public function test_komponen_bisa_menunjuk_bahan_bakunya(): void
    {
        $pipa = ProductionItem::create([
            'sku' => 'PIP-28',
            'name' => 'Pipa SS 201 Ø28',
            'shape' => 'linear',
            'unit' => 'batang',
            'length_mm' => 6_000,
            'cost_price' => 90_000,
        ]);

        $p1 = ExhaustComponent::where('name', 'P1')->sole();
        $p1->update(['production_item_id' => $pipa->id]);

        $this->assertSame('Pipa SS 201 Ø28', $p1->refresh()->displayMaterial());
        $this->assertNotContains('P1', ExhaustComponent::tanpaBahan()->pluck('name'));
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
