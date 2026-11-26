<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Peran dibuat sendiri dengan centang fitur (menu) yang boleh dibuka. */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'owner']));
    }

    public function test_owner_creates_a_role_with_chosen_features(): void
    {
        $this->get(route('admin.users.index'))->assertOk()->assertSee('Peran &amp; hak akses', false)->assertSee('Penerimaan');

        $this->post(route('admin.users.roles.store'), ['name' => 'Admin Pembelian', 'description' => 'Belanja & terima barang'])->assertSessionHasErrors('permissions');
        $this->post(route('admin.users.roles.store'), ['name' => 'Admin Pembelian', 'permissions' => ['purchases', 'receipts', 'finish-good']])->assertSessionHasNoErrors();
        $role = Role::where('name', 'Admin Pembelian')->firstOrFail();
        $this->assertSame('admin-pembelian', $role->key);

        $this->post(route('admin.users.store'), ['name' => 'Rina', 'email' => 'rina@xbt.test', 'role' => $role->key, 'password' => 'rahasia123'])->assertSessionHasNoErrors();
        $rina = User::where('email', 'rina@xbt.test')->firstOrFail();

        $this->actingAs($rina);
        $this->get(route('admin.purchases.index'))->assertOk();
        $this->get(route('admin.receipts.index'))->assertOk();
        $this->get(route('admin.warehouses.show', 'finish-good'))->assertOk();
        $this->get(route('admin.warehouses.show', 'bahan-baku'))->assertForbidden();
        $this->get(route('admin.suppliers.index'))->assertForbidden();
        $this->get(route('admin.sales.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Penerimaan')->assertDontSee('Jasa Vendor');

        // Pemilik menambah fitur Supplier → langsung bisa dibuka.
        $this->actingAs(User::where('role', 'owner')->firstOrFail());
        $this->put(route('admin.users.roles.update', $role), ['name' => 'Admin Pembelian', 'permissions' => ['purchases', 'receipts', 'suppliers']])->assertSessionHasNoErrors();
        $this->actingAs($rina->fresh());
        $this->get(route('admin.suppliers.index'))->assertOk();
    }

    public function test_all_access_roles_and_protected_roles(): void
    {
        $this->post(route('admin.users.roles.store'), ['name' => 'Manajer', 'all_access' => 1])->assertSessionHasNoErrors();
        $manager = User::factory()->create(['role' => 'manajer']);

        $this->actingAs($manager);
        $this->get(route('admin.sales.index'))->assertOk();
        $this->get(route('admin.finance.index'))->assertOk();
        $this->get(route('admin.users.index'))->assertForbidden();

        $this->actingAs(User::where('role', 'owner')->firstOrFail());
        $owner = Role::where('key', 'owner')->firstOrFail();
        $this->put(route('admin.users.roles.update', $owner), ['name' => 'Pemilik', 'permissions' => ['sales']])->assertSessionHas('error');
        $this->delete(route('admin.users.roles.destroy', $owner))->assertSessionHas('error');
        $this->delete(route('admin.users.roles.destroy', Role::where('key', 'manajer')->firstOrFail()))->assertSessionHas('error');
        $this->assertModelExists(Role::where('key', 'manajer')->firstOrFail());

        $manager->delete();
        $this->delete(route('admin.users.roles.destroy', Role::where('key', 'manajer')->firstOrFail()))->assertSessionHasNoErrors();
        $this->assertNull(Role::where('key', 'manajer')->first());
    }
}
