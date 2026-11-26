<?php

namespace Tests\Feature;

use App\Models\CostOption;
use App\Models\Formula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Data Ongkos: nama ongkos formula + tarif bawaan sebagai master data. */
class CostOptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_cost_master_feeds_the_formula_form_and_learns_new_costs(): void
    {
        $this->assertTrue(CostOption::where('name', 'Ongkos las')->exists());

        // Tambah cepat dari form formula (tombol +).
        $this->postJson(route('admin.cost-options.store'), ['name' => 'Ongkos poles', 'amount' => 7_500])
            ->assertOk()->assertJson(['name' => 'Ongkos poles', 'amount' => 7_500]);
        $this->get(route('admin.formulas.create'))->assertOk()
            ->assertViewHas('costOptions', fn ($options) => $options->contains(fn ($o) => $o['name'] === 'Ongkos poles' && $o['amount'] == 7_500));

        // Ongkos baru yang diketik saat menyimpan formula ikut tercatat.
        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'Matic', 'motor_type' => 'Mio', 'name' => 'Mio Std', 'is_active' => 1,
            'parts' => [['name' => 'Leheran', 'components' => []]],
            'costs' => [['name' => 'Ongkos packing kayu', 'amount' => 12_000]],
        ])->assertSessionHasNoErrors();
        $packing = CostOption::findByName('Ongkos packing kayu');
        $this->assertEquals(12_000, (float) $packing->amount);

        // Ganti nama di master → nama ongkos di formula ikut.
        $this->put(route('admin.cost-options.update', $packing), ['name' => 'Packing peti kayu', 'amount' => 12_000])->assertSessionHasNoErrors();
        $this->assertSame('Packing peti kayu', Formula::firstOrFail()->costs()->first()->name);
        $this->get(route('admin.cost-options.index'))->assertOk()->assertSee('Packing peti kayu');

        $this->delete(route('admin.cost-options.destroy', $packing->fresh()))->assertSessionHasNoErrors();
        $this->assertSame('Packing peti kayu', Formula::firstOrFail()->costs()->first()->name, 'Formula tidak berubah saat dihapus dari daftar.');
    }
}
