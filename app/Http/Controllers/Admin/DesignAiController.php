<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignSimulation;
use App\Services\DesignAi;
use App\Support\ExhaustSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fitur AI Simulasi Desain (OpenAI, key di server): pasang otomatis di foto,
 * baca foto referensi, dan render foto-realistis knalpot terpasang di motor.
 */
class DesignAiController extends Controller
{
    private const MAX_RENDERS = 12;

    public function __construct(private DesignAi $ai) {}

    /** Posisi roda belakang & arah motor di foto (untuk pasang otomatis). */
    public function locate(Request $request): JsonResponse
    {
        $data = $request->validate(['photo' => ['required', 'image', 'max:10240']]);

        return $this->call(fn () => $this->ai->locateExhaustMount(...$this->read($data['photo'])));
    }

    /** Bentuk, finishing & proporsi knalpot di foto referensi. */
    public function reference(Request $request): JsonResponse
    {
        $data = $request->validate(['image' => ['required', 'image', 'max:10240']]);

        return $this->call(fn () => $this->ai->readReference(...$this->read($data['image'])));
    }

    /** Foto motor + gambar knalpot → foto-realistis knalpot terpasang. */
    public function render(Request $request): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'max:10240'],
            'exhaust' => ['required', 'image', 'max:10240'],
            'motor' => ['nullable', 'string', 'max:40'],
            'hint' => ['nullable', 'string', 'max:300'],
            'design_id' => ['nullable', 'integer', 'exists:design_simulations,id'],
        ]);
        $motorName = ExhaustSimulator::MOTORS[$data['motor'] ?? '']['label'] ?? 'skuter matic';

        return $this->call(function () use ($data, $motorName) {
            $png = $this->ai->render(...[...$this->read($data['photo']), ...$this->read($data['exhaust']), $motorName, (string) ($data['hint'] ?? '')]);
            $path = 'uploads/desain/render/render-'.now()->format('Ymd-His').'-'.Str::random(8).'.png';
            Storage::disk('site')->put($path, $png);

            if ($design = isset($data['design_id']) ? DesignSimulation::find($data['design_id']) : null) {
                $renders = [$path, ...($design->renders ?? [])];
                foreach (array_slice($renders, self::MAX_RENDERS) as $old) { // simpan 12 terakhir
                    Storage::disk('site')->delete($old);
                }
                $design->forceFill(['renders' => array_slice($renders, 0, self::MAX_RENDERS)])->save();
            }

            return ['url' => asset($path), 'path' => $path, 'saved' => isset($design)];
        });
    }

    /** @return array{0: string, 1: string} isi file & tipe MIME */
    private function read(UploadedFile $file): array
    {
        return [(string) file_get_contents($file->getRealPath()), $file->getMimeType() ?: 'image/png'];
    }

    private function call(callable $work): JsonResponse
    {
        if (! $this->ai->enabled()) {
            return response()->json(['message' => 'Fitur AI belum aktif: isi OPENAI_API_KEY di .env server.'], 503);
        }

        try {
            return response()->json($work());
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
