<?php

namespace Tests\Feature;

use App\Models\DesignSimulation;
use App\Models\User;
use App\Support\ExhaustSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Simulasi Desain: hitungan tenaga, desain otomatis, simpan dengan foto referensi & foto motor. */
class DesignSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'owner']));
    }

    public function test_tuned_design_gains_power_and_bad_design_gets_concrete_advice(): void
    {
        $good = ExhaustSimulator::simulate(ExhaustSimulator::autoDesign('mio', 'gp'));

        $this->assertEqualsWithDelta(558, $good['ideal']['header_length'], 2, 'L = 850×ED/rpm − 3 untuk 8.000 rpm');
        $this->assertEqualsWithDelta(8000, $good['tuned_rpm'], 150);
        $this->assertGreaterThan(0.4, $good['gain_hp']);
        $this->assertLessThan(1.0, $good['gain_hp'], 'Knalpot saja tidak menambah lebih dari ±10% di matic standar');
        $this->assertGreaterThanOrEqual(75, $good['fit']['overall']);
        $this->assertGreaterThanOrEqual(280, $good['params']['silencer']['length']);

        $bad = ExhaustSimulator::simulate(['motor' => 'mio', 'header' => ['diameter' => 38, 'length' => 350], 'silencer' => ['shape' => 'round', 'core' => 22, 'length' => 180, 'width' => 60]]);
        $this->assertLessThan(10, $bad['fit']['overall']);
        $this->assertLessThan($good['gain_hp'], $bad['gain_hp']);
        $advice = implode(' ', $bad['advice']);
        foreach (['Perpanjang leheran', 'Diameter leheran terlalu besar', 'Core silincer', 'Silincer kecil'] as $tip) {
            $this->assertStringContainsString($tip, $advice);
        }
    }

    public function test_parameters_are_clamped_and_mockup_defaults_filled(): void
    {
        $p = ExhaustSimulator::normalize(['motor' => 'hacker', 'header' => ['diameter' => 999, 'length' => -5], 'mockup' => ['x' => 9, 'flip' => '1']]);

        $this->assertSame('mio', $p['motor']);
        $this->assertEquals(60, $p['header']['diameter']);
        $this->assertEquals(250, $p['header']['length']);
        $this->assertEquals(1.5, $p['mockup']['x']);
        $this->assertTrue($p['mockup']['flip']);
        $this->assertSame(ExhaustSimulator::MOCKUP_DEFAULT['scale'], $p['mockup']['scale']);
    }

    public function test_editor_pages_and_live_endpoints(): void
    {
        $this->get(route('admin.design-sims.index'))->assertOk()->assertSee('Buat desain otomatis');
        $this->get(route('admin.design-sims.create', ['motor' => 'nmax', 'gaya' => 'megaphone']))->assertOk()
            ->assertSee('data-design-sim', false)->assertSee('Di motor')->assertSee('Yamaha NMAX 155');

        $this->postJson(route('admin.design-sims.simulate'), ['params' => ['motor' => 'vario-160', 'header' => ['length' => 520]]])
            ->assertOk()->assertJsonPath('engine.hp', 15.4)->assertJsonStructure(['gain_hp', 'tuned_rpm', 'curve' => [['rpm', 'stock', 'modified']], 'advice']);

        $this->postJson(route('admin.design-sims.auto'), ['motor' => 'beat', 'style' => 'hexagon'])
            ->assertOk()->assertJsonPath('params.silencer.shape', 'hexagon')->assertJsonPath('params.motor', 'beat');
        $this->postJson(route('admin.design-sims.auto'), ['motor' => 'beat', 'style' => 'roket'])->assertUnprocessable();
    }

    public function test_design_saves_with_reference_and_motor_photos_then_cleans_up_on_delete(): void
    {
        Storage::fake('site');
        $params = ExhaustSimulator::autoDesign('mio', 'gp');
        $params['mockup'] = ['photo' => 0, 'x' => 0.4, 'y' => 0.8, 'scale' => 0.5, 'rotation' => 3, 'flip' => true, 'brightness' => 0.8];
        $thumbnail = 'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('p.png', 40, 20)->getContent());

        $this->post(route('admin.design-sims.store'), [
            'name' => 'Racing Mio GP',
            'params' => json_encode($params),
            'references' => [UploadedFile::fake()->image('contoh.jpg')],
            'motor_photos' => [UploadedFile::fake()->image('mio-samping.jpg', 1200, 800)],
            'thumbnail' => $thumbnail,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $design = DesignSimulation::firstOrFail();
        $this->assertSame('mio', $design->motor);
        $this->assertTrue($design->params['mockup']['flip']);
        $this->assertSame(0.8, (float) $design->params['mockup']['brightness']);
        $this->assertCount(1, $design->references);
        $this->assertCount(1, $design->motor_photos);
        $files = [...$design->references, ...$design->motor_photos, $design->thumbnail];
        foreach ($files as $path) {
            Storage::disk('site')->assertExists($path);
        }

        $this->get(route('admin.design-sims.edit', $design))->assertOk()
            ->assertSee(asset($design->motor_photos[0]), false)->assertSee('Unduh gambar di motor')
            ->assertSee('Pasang otomatis')->assertSee('Pasang dengan 2 ketukan')->assertSee('data-apply-ref="'.asset($design->references[0]).'"', false);
        $this->get(route('admin.design-sims.index'))->assertSee('Racing Mio GP')->assertSee(asset($design->thumbnail), false);

        // Thumbnail palsu (bukan gambar) diabaikan; foto motor bisa dihapus.
        $this->put(route('admin.design-sims.update', $design), [
            'name' => 'Racing Mio GP v2', 'params' => json_encode($params),
            'thumbnail' => 'data:image/png;base64,'.base64_encode('<?php echo 1; ?>'),
            'remove_motor_photos' => [$design->motor_photos[0], 'uploads/lain/milik-orang.jpg'],
        ])->assertSessionHasNoErrors();
        $design->refresh();
        $this->assertSame([], $design->motor_photos);
        Storage::disk('site')->assertMissing($files[1]);
        Storage::disk('site')->assertExists($design->thumbnail);

        $this->delete(route('admin.design-sims.destroy', $design))->assertRedirect(route('admin.design-sims.index'));
        $this->assertModelMissing($design);
        Storage::disk('site')->assertMissing($files[0]);
        Storage::disk('site')->assertMissing($files[2]);
    }

    public function test_reference_photo_can_become_the_active_design(): void
    {
        Storage::fake('site');
        $params = ExhaustSimulator::autoDesign('mio', 'gp');
        $params['source'] = ['type' => 'reference', 'index' => 0, 'share' => 0.8];
        $params['mockup']['body_color'] = '#ff0000';

        $this->post(route('admin.design-sims.store'), [
            'name' => 'Dari referensi', 'params' => json_encode($params),
            'references' => [UploadedFile::fake()->image('slip-on.png')],
        ])->assertSessionHasNoErrors();
        $design = DesignSimulation::firstOrFail();
        $this->assertSame(['type' => 'reference', 'index' => 0, 'share' => 0.8], $design->params['source']);
        $this->assertSame('#ff0000', $design->params['mockup']['body_color']);
        $this->get(route('admin.design-sims.edit', $design))->assertOk()->assertSee('Desain dari foto referensi')->assertSee('Motor 3D');

        // Foto referensinya dihapus → kembali ke desain parametrik.
        $this->put(route('admin.design-sims.update', $design), [
            'name' => 'Dari referensi', 'params' => json_encode($params), 'remove_references' => $design->references,
        ])->assertSessionHasNoErrors();
        $this->assertSame(['type' => 'parametric'], $design->fresh()->params['source']);
        $this->assertSame('parametric', ExhaustSimulator::normalize(['source' => ['type' => 'hack']])['source']['type']);
    }

    public function test_menu_access_follows_role_permissions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'kasir']));
        $this->get(route('admin.design-sims.index'))->assertForbidden();
        $this->postJson(route('admin.design-sims.simulate'), ['params' => []])->assertForbidden();
    }
}
