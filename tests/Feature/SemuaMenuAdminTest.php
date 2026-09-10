<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Buka setiap menu admin, satu per satu.
 *
 * Ada di sini karena kelas kesalahan yang nyata: menghapus sebuah model lalu
 * meninggalkan satu berkas yang masih memanggilnya. Lint lolos, suite lolos,
 * dan yang menemukan justru pengguna — layar putih bertuliskan "Failed to open
 * stream". Test ini menyentuh tiap halaman daftar, jadi rujukan yang menggantung
 * ketahuan sebelum sampai ke orang.
 */
class SemuaMenuAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_setiap_halaman_daftar_resource_bisa_dibuka(): void
    {
        $resources = Filament::getPanel('admin')->getResources();

        $this->assertNotEmpty($resources, 'Panel admin tidak punya resource sama sekali.');

        foreach ($resources as $resource) {
            $url = $resource::getUrl('index');
            $balasan = $this->get($url);

            $this->assertTrue(
                $balasan->isSuccessful(),
                sprintf('Menu %s (%s) balas HTTP %d.', class_basename($resource), $url, $balasan->status())
            );
        }
    }

    public function test_setiap_halaman_mandiri_bisa_dibuka(): void
    {
        foreach (Filament::getPanel('admin')->getPages() as $page) {
            $url = $page::getUrl();
            $balasan = $this->get($url);

            $this->assertTrue(
                $balasan->isSuccessful(),
                sprintf('Halaman %s (%s) balas HTTP %d.', class_basename($page), $url, $balasan->status())
            );
        }
    }
}
