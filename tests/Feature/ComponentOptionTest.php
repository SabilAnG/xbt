<?php

namespace Tests\Feature;

use App\Models\ComponentOption;
use App\Models\Formula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Data Komponen: bagian & komponen formula sebagai master data, bukan lagi di kode. */
class ComponentOptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_new_formula_uses_parts_and_suggestions_from_the_master(): void
    {
        $this->assertSame(['Leheran', 'Silincer', 'Aksesoris'], ComponentOption::defaultParts(Formula::KNALPOT));
        $this->assertContains('Pureng', ComponentOption::suggestions()['Leheran']);

        // Bagian baru yang dijadikan bawaan ikut muncul di formula baru.
        $this->post(route('admin.component-options.store'), ['kind' => 'bagian', 'name' => 'Dudukan'])->assertSessionHasNoErrors();
        $mount = ComponentOption::findPart('Dudukan');
        $this->put(route('admin.component-options.update', $mount), ['name' => 'Dudukan', 'sort_order' => 4, 'default_for' => 'knalpot'])->assertSessionHasNoErrors();

        $this->get(route('admin.formulas.create'))->assertOk()
            ->assertViewHas('parts', fn (array $parts) => collect($parts)->pluck('name')->all() === ['Leheran', 'Silincer', 'Aksesoris', 'Dudukan']);
        $this->get(route('admin.component-options.index'))->assertOk()->assertSee('Dudukan')->assertSee('Sok sambungan');
    }

    public function test_quick_add_saving_a_formula_and_renames(): void
    {
        // Tombol "+" di komponen formula.
        $this->postJson(route('admin.component-options.store'), ['kind' => 'komponen', 'part' => 'Leheran', 'name' => 'P6'])
            ->assertOk()->assertJson(['name' => 'P6']);
        $this->postJson(route('admin.component-options.store'), ['kind' => 'komponen', 'part' => 'leheran', 'name' => 'p6'])->assertJson(['name' => 'P6']);
        $this->assertSame(1, ComponentOption::findPart('Leheran')->components()->where('name', 'P6')->count());

        // Nama yang diketik saat menyimpan formula ikut tercatat.
        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'Matic', 'motor_type' => 'Mio', 'name' => 'Mio Std', 'is_active' => 1,
            'parts' => [['name' => 'Leheran', 'components' => [['name' => 'Leher custom', 'qty' => 1, 'materials' => []]]]],
        ])->assertSessionHasNoErrors();
        $custom = ComponentOption::findPart('Leheran')->components()->where('name', 'Leher custom')->firstOrFail();

        // Ganti nama komponen → formula yang memakainya ikut berubah.
        $this->put(route('admin.component-options.update', $custom), ['name' => 'Leher kustom'])->assertSessionHasNoErrors();
        $this->assertSame('Leher kustom', Formula::firstOrFail()->parts()->first()->components()->first()->name);

        // Menghapus dari daftar tidak mengubah formula.
        $this->delete(route('admin.component-options.destroy', $custom->fresh()))->assertSessionHasNoErrors();
        $this->assertSame('Leher kustom', Formula::firstOrFail()->parts()->first()->components()->first()->name);
    }
}
