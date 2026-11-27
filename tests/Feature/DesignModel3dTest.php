<?php

namespace Tests\Feature;

use App\Models\DesignSimulation;
use App\Models\User;
use App\Support\ExhaustSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Foto knalpot/motor → model 3D 360° lewat Tripo3D (respons disimulasikan). */
class DesignModel3dTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['services.tripo.key' => 'tsk-test', 'services.tripo.model' => 'v3.1-20260211']);
        $this->actingAs(User::factory()->create(['role' => 'owner']));
    }

    public function test_photo_becomes_a_downloaded_glb_model(): void
    {
        Storage::fake('site');
        Http::fake([
            'openapi.tripo3d.ai/v3/files' => Http::response(['code' => 0, 'data' => ['file_token' => 'file_abc']]),
            'openapi.tripo3d.ai/v3/generation/image-to-model' => Http::response(['code' => 0, 'data' => ['task_id' => 'task-123e4567']]),
            'openapi.tripo3d.ai/v3/tasks/task-123e4567' => Http::sequence()
                ->push(['code' => 0, 'data' => ['status' => 'running', 'progress' => 40]])
                ->push(['code' => 0, 'data' => ['status' => 'success', 'progress' => 100, 'output' => ['model_url' => 'https://cdn.tripo3d.ai/output/model_pbr.glb']]]),
            'cdn.tripo3d.ai/*' => Http::response('glTF'.str_repeat("\0", 32)),
        ]);

        $this->post(route('admin.design-sims.model3d.start'), ['image' => UploadedFile::fake()->image('knalpot.png', 800, 300), 'kind' => 'exhaust'])
            ->assertOk()->assertJsonPath('task_id', 'task-123e4567');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/generation/image-to-model')
            && $r['input'] === 'file_abc' && $r['model'] === 'v3.1-20260211' && $r['pbr'] === true && $r['enable_image_autofix'] === false
            && $r->hasHeader('Authorization', 'Bearer tsk-test'));

        $this->getJson(route('admin.design-sims.model3d.poll', 'task-123e4567'))->assertOk()->assertJsonPath('status', 'running')->assertJsonPath('progress', 40);
        $done = $this->getJson(route('admin.design-sims.model3d.poll', 'task-123e4567'))->assertOk()->assertJsonPath('status', 'success');
        $path = $done->json('path');
        $this->assertSame('uploads/desain/3d/task-123e4567.glb', $path);
        Storage::disk('site')->assertExists($path);

        // Sudah diunduh: polling berikutnya tidak menghubungi Tripo lagi.
        $this->getJson(route('admin.design-sims.model3d.poll', 'task-123e4567'))->assertJsonPath('path', $path);

        // Tersimpan di desain; hapus desain = hapus file 3D.
        $params = ExhaustSimulator::autoDesign('mio', 'gp');
        $params['models'] = ['exhaust' => $path, 'motor' => '../../.env', 'motor_flip' => true, 'offset' => ['x' => 999, 'y' => 20, 'z' => -10]];
        $this->post(route('admin.design-sims.store'), ['name' => 'Knalpot 3D', 'params' => json_encode($params)])->assertSessionHasNoErrors();
        $design = DesignSimulation::firstOrFail();
        $this->assertSame($path, $design->params['models']['exhaust']);
        $this->assertNull($design->params['models']['motor'], 'Path di luar folder 3D ditolak');
        $this->assertEquals(400, $design->params['models']['offset']['x']);
        $this->get(route('admin.design-sims.edit', $design))->assertSee('Jadikan motor 3D')->assertSee('Penyesuaian model 3D dari foto');
        $this->delete(route('admin.design-sims.destroy', $design));
        Storage::disk('site')->assertMissing($path);
    }

    public function test_failures_are_explained(): void
    {
        Http::fake([
            'openapi.tripo3d.ai/v3/files' => Http::response(['code' => 2010, 'status' => 'error', 'message' => 'Insufficient credits'], 403),
            'openapi.tripo3d.ai/v3/tasks/*' => Http::response(['code' => 0, 'data' => ['status' => 'failed', 'error_message' => 'image unclear']]),
        ]);

        $this->post(route('admin.design-sims.model3d.start'), ['image' => UploadedFile::fake()->image('motor.jpg'), 'kind' => 'motor'])
            ->assertUnprocessable()->assertJsonPath('message', 'Tripo3D: Kredit Tripo3D tidak cukup. Isi ulang di developers.tripo3d.ai.');
        $this->getJson(route('admin.design-sims.model3d.poll', 'task-abcdef12'))->assertUnprocessable()
            ->assertJsonPath('message', 'Pembuatan model 3D gagal: image unclear.');
        $this->getJson('/admin/simulasi-desain/3d/'.urlencode('../x'))->assertNotFound();

        config(['services.tripo.key' => null]);
        $this->post(route('admin.design-sims.model3d.start'), ['image' => UploadedFile::fake()->image('m.jpg'), 'kind' => 'motor'])->assertStatus(503);
        $this->get(route('admin.design-sims.create'))->assertSee('isi TRIPO_API_KEY');
    }
}
