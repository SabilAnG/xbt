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

/** Fitur AI Simulasi Desain lewat OpenAI (respons OpenAI disimulasikan). */
class DesignAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['services.openai.key' => 'sk-test', 'services.openai.vision_model' => 'gpt-vision-test', 'services.openai.image_model' => 'gpt-image-test']);
        $this->actingAs(User::factory()->create(['role' => 'owner']));
    }

    private function visionReply(array $json): array
    {
        return ['choices' => [['message' => ['content' => json_encode($json)]]]];
    }

    public function test_ai_locates_the_rear_wheel_for_auto_mounting(): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response($this->visionReply([
            'found' => true, 'rear_wheel' => ['x' => 0.29, 'y' => 0.76, 'r' => 0.09], 'facing' => 'right', 'confidence' => 0.88,
        ]))]);

        $this->post(route('admin.design-sims.ai.locate'), ['photo' => UploadedFile::fake()->image('mio.jpg', 800, 540)])
            ->assertOk()->assertJsonPath('facing', 'right')->assertJsonPath('rear_wheel.x', 0.29);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer sk-test')
            && $r['model'] === 'gpt-vision-test'
            && $r['response_format']['json_schema']['strict'] === true
            && str_starts_with($r['messages'][0]['content'][1]['image_url']['url'], 'data:image/'));
    }

    public function test_ai_reads_reference_and_reports_unrecognised_photos(): void
    {
        Http::fakeSequence('api.openai.com/*')
            ->push($this->visionReply(['is_exhaust' => true, 'shape' => 'hexagon', 'finish' => 'satin', 'diameter_ratio' => 0.27, 'tip' => 'cap', 'silencer_share' => 0.78, 'summary' => 'Silincer hexagon satin.']))
            ->push($this->visionReply(['is_exhaust' => false, 'shape' => 'gp', 'finish' => 'satin', 'diameter_ratio' => 0.3, 'tip' => 'cap', 'silencer_share' => 1, 'summary' => '-']));

        $this->post(route('admin.design-sims.ai.reference'), ['image' => UploadedFile::fake()->image('akra.png')])
            ->assertOk()->assertJsonPath('shape', 'hexagon')->assertJsonPath('tip', 'cap');
        $this->post(route('admin.design-sims.ai.reference'), ['image' => UploadedFile::fake()->image('kucing.png')])
            ->assertUnprocessable()->assertJsonPath('message', 'AI tidak mengenali knalpot di foto ini.');
    }

    public function test_ai_render_is_saved_on_the_design(): void
    {
        Storage::fake('site');
        $png = UploadedFile::fake()->image('hasil.png', 64, 40)->getContent();
        Http::fake(['api.openai.com/v1/images/edits' => Http::response(['data' => [['b64_json' => base64_encode($png)]]])]);
        $design = DesignSimulation::create(['name' => 'Mio GP', 'motor' => 'mio', 'params' => ExhaustSimulator::autoDesign('mio', 'gp')]);

        $response = $this->post(route('admin.design-sims.ai.render'), [
            'photo' => UploadedFile::fake()->image('mio.jpg', 800, 540),
            'exhaust' => UploadedFile::fake()->image('knalpot.png', 600, 200),
            'motor' => 'mio', 'hint' => 'silincer 30% dari kiri', 'design_id' => $design->id,
        ])->assertOk()->assertJsonPath('saved', true);

        $path = $response->json('path');
        Storage::disk('site')->assertExists($path);
        $this->assertSame([$path], $design->fresh()->renders);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/images/edits') && $r->isMultipart()
            && collect($r->data())->contains(fn ($part) => $part['name'] === 'model' && $part['contents'] === 'gpt-image-test')
            && collect($r->data())->where('name', 'image[]')->count() === 2);
        $this->get(route('admin.design-sims.edit', $design))->assertSee('Render tersimpan')->assertSee(asset($path), false);
    }

    public function test_ai_errors_and_missing_key_are_explained(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided']], 401)]);
        $this->post(route('admin.design-sims.ai.locate'), ['photo' => UploadedFile::fake()->image('mio.jpg')])
            ->assertUnprocessable()->assertJsonPath('message', 'OpenAI menolak permintaan: Incorrect API key provided');

        config(['services.openai.key' => null]);
        $this->post(route('admin.design-sims.ai.locate'), ['photo' => UploadedFile::fake()->image('mio.jpg')])->assertStatus(503);
        $this->get(route('admin.design-sims.create'))->assertSee('Belum aktif: isi OPENAI_API_KEY');

        $this->actingAs(User::factory()->create(['role' => 'kasir']));
        $this->post(route('admin.design-sims.ai.render'), [])->assertForbidden();
    }
}
