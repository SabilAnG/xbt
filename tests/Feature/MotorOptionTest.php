<?php

namespace Tests\Feature;

use App\Models\Formula;
use App\Models\MotorOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Master data motor (kategori, brand, type, varian) dan tambah cepat dari form formula. */
class MotorOptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_quick_add_from_the_formula_form_returns_the_canonical_name(): void
    {
        $this->postJson(route('admin.motor-options.store'), ['kind' => 'brand', 'name' => 'Benelli'])
            ->assertOk()->assertJson(['name' => 'Benelli']);

        // Nama yang sama dengan huruf berbeda tidak membuat data ganda.
        $this->postJson(route('admin.motor-options.store'), ['kind' => 'brand', 'name' => 'benelli'])
            ->assertOk()->assertJson(['name' => 'Benelli']);
        $this->assertSame(1, MotorOption::where('kind', 'brand')->where('name', 'Benelli')->count());

        $this->postJson(route('admin.motor-options.store'), ['kind' => 'warna', 'name' => 'Merah'])->assertUnprocessable();
        $this->get(route('admin.formulas.create'))->assertOk()->assertSee('Benelli')->assertSee('data-option-add', false);
    }

    public function test_formula_values_are_registered_and_renames_cascade(): void
    {
        $this->post(route('admin.formulas.store'), [
            'motor_category' => 'matic', 'motor_brand' => 'Yamaha', 'motor_type' => 'Mio', 'variant' => 'Racing Sarangan Tengah',
            'name' => 'Mio Racing', 'is_active' => 1,
            'parts' => [['name' => 'Leheran', 'components' => []]],
        ])->assertSessionHasNoErrors();

        $formula = Formula::firstOrFail();
        $this->assertSame('Matic', $formula->motor_category, 'Kunci lama dibakukan ke nama master.');
        $this->assertSame('Yamaha', $formula->motor_brand);
        $this->assertTrue(MotorOption::where('kind', 'varian')->where('name', 'Racing Sarangan Tengah')->exists());

        // Ganti nama varian → formula ikut; varian yang dipakai tidak bisa dihapus.
        $variant = MotorOption::where('kind', 'varian')->where('name', 'Racing Sarangan Tengah')->firstOrFail();
        $this->put(route('admin.motor-options.update', $variant), ['name' => 'Racing ST'])->assertSessionHasNoErrors();
        $this->assertSame('Racing ST', $formula->fresh()->variant);
        $this->delete(route('admin.motor-options.destroy', $variant))->assertSessionHas('error');

        $unused = MotorOption::where('kind', 'brand')->where('name', 'Vespa')->firstOrFail();
        $this->delete(route('admin.motor-options.destroy', $unused))->assertSessionHasNoErrors();
        $this->assertModelMissing($unused);

        $this->get(route('admin.motor-options.index'))->assertOk()->assertSee('Racing ST');
        $this->get(route('admin.formulas.index', ['brand' => 'Yamaha']))->assertOk()->assertSee('Mio Racing');
    }

    public function test_motor_types_belong_to_a_brand(): void
    {
        $yamaha = MotorOption::where('kind', 'brand')->where('name', 'Yamaha')->firstOrFail();
        $this->assertSame('Yamaha', MotorOption::findByName('type', 'Mio')->parent->name);

        // Tambah cepat type dari form: ikut brand yang sedang dipilih.
        $this->postJson(route('admin.motor-options.store'), ['kind' => 'type', 'name' => 'Grand Filano', 'brand' => 'Yamaha'])
            ->assertOk()->assertJson(['name' => 'Grand Filano', 'brand' => 'Yamaha']);
        $this->assertTrue($yamaha->children()->where('name', 'Grand Filano')->exists());
        $this->postJson(route('admin.motor-options.store'), ['kind' => 'type', 'name' => 'Mio', 'brand' => 'Honda'])->assertUnprocessable();

        $this->get(route('admin.formulas.create'))->assertOk()->assertSee('data-parent="Yamaha"', false);

        // Formula: type & brand harus cocok; brand kosong terisi dari type.
        $base = ['motor_category' => 'Matic', 'is_active' => 1, 'parts' => [['name' => 'Leheran', 'components' => []]]];
        $this->post(route('admin.formulas.store'), $base + ['motor_brand' => 'Honda', 'motor_type' => 'Mio', 'name' => 'Salah'])->assertSessionHasErrors('motor_type');
        $this->post(route('admin.formulas.store'), $base + ['motor_type' => 'Mio', 'name' => 'Mio Std'])->assertSessionHasNoErrors();
        $this->assertSame('Yamaha', Formula::where('name', 'Mio Std')->value('motor_brand'));

        // Brand yang masih punya type tidak bisa dihapus; type bisa dipindah brand.
        $this->delete(route('admin.motor-options.destroy', $yamaha))->assertSessionHas('error');
        $filano = MotorOption::findByName('type', 'Grand Filano');
        $this->put(route('admin.motor-options.update', $filano), ['name' => 'Grand Filano', 'brand' => 'Honda'])->assertSessionHasNoErrors();
        $this->assertSame('Honda', $filano->fresh()->parent->name);
    }
}
