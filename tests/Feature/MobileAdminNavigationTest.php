<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tampilan HP: app bar, tab bawah, dan lembar Menu mengikuti hak akses peran. */
class MobileAdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_owner_gets_app_bar_tabs_menu_sheet_and_install_manifest(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Sidik Pratama', 'role' => 'owner']));

        $html = $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('class="m-appbar', false)
            ->assertSee('Halo, Sidik')
            ->assertSee('data-sheet="menu"', false)
            ->assertSee('data-sheet="notifications"', false)
            ->assertSee('admin.webmanifest', false)
            ->assertSee('viewport-fit=cover', false)
            // Pemilih tanggal bergaya aplikasi menggantikan kalender bawaan browser.
            ->assertSee('input.input[type="date"], input.input[type="month"], input.input[type="datetime-local"]', false)
            ->getContent();

        // Tab bawah: Ringkasan + 3 grup utama + Menu.
        preg_match('/<nav class="m-tabbar.*?<\/nav>/s', $html, $tabbar);
        foreach (['Ringkasan', 'Penjualan', 'Gudang', 'Produksi', 'Menu'] as $tab) {
            $this->assertStringContainsString('>'.$tab.'<', $tabbar[0]);
        }

        $this->assertFileExists(public_path('admin.webmanifest'));
        $manifest = json_decode(file_get_contents(public_path('admin.webmanifest')), true);
        $this->assertSame('/admin', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
    }

    public function test_detail_pages_get_a_back_button_to_their_list(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        $this->get(route('admin.sales.create'))->assertOk()
            ->assertSee('href="'.route('admin.sales.index').'" class="m-icon-btn" aria-label="Kembali"', false)
            ->assertSee('<h1 class="m-appbar-title">Input penjualan</h1>', false);
    }

    public function test_tabs_and_menu_only_show_features_the_role_may_open(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'kasir']));

        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        preg_match('/<nav class="m-tabbar.*?<\/nav>/s', $html, $tabbar);
        preg_match('/data-sheet="menu".*?data-sheet="notifications"/s', $html, $menu);

        $this->assertStringContainsString('>Penjualan<', $tabbar[0]);
        $this->assertStringNotContainsString('>Produksi<', $tabbar[0]);
        $this->assertStringNotContainsString(route('admin.users.index'), $menu[0]);
        $this->assertStringNotContainsString(route('admin.productions.index'), $menu[0]);
        $this->assertStringContainsString(route('admin.account.security'), $menu[0]);
    }
}
