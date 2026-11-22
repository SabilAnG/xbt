<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Database\Seeders\KnalpotWorkbookSeeder;
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

    public function test_every_admin_page_renders_with_workbook_data(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Rp 24.859.000')->assertSee('Rp 104.015.888');

        foreach ([
            'admin.items.index', 'admin.items.create', 'admin.materials.index', 'admin.materials.create',
            'admin.formulas.index', 'admin.formulas.create', 'admin.productions.index', 'admin.productions.create',
            'admin.sales.index', 'admin.sales.create', 'admin.purchases.index', 'admin.purchases.create',
            'admin.adjustments.index', 'admin.adjustments.create', 'admin.expenses.index', 'admin.expenses.create',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->get(route('admin.items.show', 1))->assertOk();
        $this->get(route('admin.materials.show', 1))->assertOk();
        $this->get(route('admin.formulas.edit', 1))->assertOk();
        $this->get(route('admin.productions.show', 1))->assertOk();
        $this->get(route('admin.sales.edit', 1))->assertOk();
        $this->get(route('admin.expenses.edit', 1))->assertOk();
    }

    public function test_recording_production_through_the_form_updates_stock(): void
    {
        $this->seed(KnalpotWorkbookSeeder::class);
        $this->actingAs(User::factory()->create());
        $item = Item::where('sku', 'KNL-009')->firstOrFail();

        $this->post(route('admin.productions.store'), [
            'produced_at' => '2026-11-20',
            'item_id' => $item->id,
            'qty' => 4,
            'worker' => 'SIDIK',
        ])->assertRedirect();

        $this->assertEquals(4, (float) $item->fresh()->stock);
        $this->assertDatabaseHas('productions', ['item_id' => $item->id, 'worker' => 'SIDIK']);
    }
}
