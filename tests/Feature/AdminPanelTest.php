<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_admin_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('admin.logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_menu_follows_the_ems_order_and_warehouse_pages_light_up_their_own_item(): void
    {
        $groups = collect(AdminNavigation::groups())->pluck('label')->all();
        $purchasing = collect(AdminNavigation::groups())->firstWhere('label', 'Pembelian')['items'];
        $this->assertSame(['Pembelian', 'Penerimaan', 'Supplier'], array_column($purchasing, 'label'));
        $this->assertSame(['Ringkasan', 'Pembelian', 'Penjualan', 'Katalog', 'Keuangan', 'Gudang', 'Produksi', 'Pekerja', 'Website', 'Pengguna'], $groups);

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.warehouses.show', 'finish-good'));
        $current = AdminNavigation::current(request());
        $this->assertSame(['Penjualan', 'Stok Siap Jual'], [$current['group'], $current['item']['label']]);

        $this->get(route('admin.warehouses.show', 'di-vendor'));
        $this->assertSame('Vendor Produksi', AdminNavigation::current(request())['item']['label']);

        $this->get(route('admin.warehouses.show', 'bahan-baku'));
        $this->assertSame('Gudang', AdminNavigation::current(request())['item']['label']);
    }

    public function test_every_admin_page_renders_on_an_empty_database(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.dashboard'))->assertOk();

        foreach ([
            'admin.items.index', 'admin.items.create', 'admin.materials.index', 'admin.materials.create',
            'admin.formulas.index', 'admin.formulas.create', 'admin.plans.index', 'admin.plans.create',
            'admin.productions.index', 'admin.productions.create', 'admin.sales.index', 'admin.sales.create',
            'admin.purchases.index', 'admin.purchases.create', 'admin.adjustments.index', 'admin.adjustments.create',
            'admin.warehouses.index', 'admin.finance.index', 'admin.expenses.index', 'admin.expenses.create',
            'admin.wallets.index', 'admin.brands.index', 'admin.production-settings.edit', 'admin.master-items.index', 'admin.units.index',
            'admin.receipts.index', 'admin.suppliers.index', 'admin.transfers.index', 'admin.transfers.create', 'admin.motor-options.index', 'admin.component-options.index', 'admin.vendor-services.index', 'admin.cost-options.index', 'admin.workers.index', 'admin.workers.create', 'admin.attendances.index', 'admin.payrolls.index',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }

        foreach (['bahan-baku', 'setengah-jadi', 'aksesoris', 'stok-sisa', 'di-vendor', 'finish-good'] as $type) {
            $this->get(route('admin.warehouses.show', $type))->assertOk();
        }
    }
}
