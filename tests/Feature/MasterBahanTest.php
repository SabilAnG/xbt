<?php

namespace Tests\Feature;

use App\Filament\Resources\Materials\Pages\CreateMaterial;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dua sumbu master bahan: didapat dari mana, dan apa perannya di produk.
 *
 * Keduanya sengaja terpisah dari kategori. Kategori memuat JENIS bahan (Pipa,
 * Plat, Hardware); menumpuk ketiganya di satu kolom akan memaksa kategori
 * seperti "Pipa Aksesoris Beli" yang beranak-pinak.
 */
class MasterBahanTest extends TestCase
{
    use RefreshDatabase;

    public function test_bahan_baru_bawaannya_dibeli_dan_bahan_utama(): void
    {
        // Itu keadaan seluruh bahan yang sudah ada, jadi data lama tidak
        // berubah arti begitu kolomnya ditambahkan.
        $bahan = Material::create([
            'sku' => 'PIP-900',
            'name' => 'Pipa Uji',
            'dimension_type' => 'linear',
            'unit' => 'batang',
            'length_mm' => 6_000,
        ]);

        $this->assertSame('beli', $bahan->refresh()->source);
        $this->assertSame('utama', $bahan->role);
        $this->assertSame('Dibeli', $bahan->displaySource());
        $this->assertSame('Bahan Utama', $bahan->displayRole());
    }

    public function test_sumber_menentukan_cara_menutup_kekurangan(): void
    {
        $dibeli = $this->bahan('beli');
        $dibuat = $this->bahan('produksi');
        $keduanya = $this->bahan('beli_produksi');

        $this->assertTrue($dibeli->bisaDibeli());
        $this->assertFalse($dibeli->bisaDiproduksi());

        $this->assertFalse($dibuat->bisaDibeli());
        $this->assertTrue($dibuat->bisaDiproduksi());

        // Yang bisa dua-duanya tidak boleh memaksa salah satu — pilihannya
        // milik orang, bukan sistem.
        $this->assertTrue($keduanya->bisaDibeli());
        $this->assertTrue($keduanya->bisaDiproduksi());
    }

    public function test_form_bahan_menampilkan_sumber_dan_peran(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateMaterial::class)
            ->assertSuccessful()
            ->assertSee('Didapat dari')
            ->assertSee('Perannya di produk')
            ->assertSee('Dibeli atau dibuat sendiri')
            ->assertSee('Aksesoris');
    }

    private function bahan(string $sumber): Material
    {
        return Material::create([
            'sku' => 'BHN-'.$sumber,
            'name' => 'Bahan '.$sumber,
            'dimension_type' => 'count',
            'unit' => 'pcs',
            'source' => $sumber,
        ]);
    }
}
