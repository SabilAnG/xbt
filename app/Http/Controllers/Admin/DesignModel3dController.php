<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Tripo3d;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Foto knalpot / motor → model 3D (GLB) 360° lewat Tripo3D. */
class DesignModel3dController extends Controller
{
    public function __construct(private Tripo3d $tripo) {}

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:20480'],
            'kind' => ['required', 'in:exhaust,motor'],
        ]);

        return $this->call(fn () => ['task_id' => $this->tripo->start(
            (string) file_get_contents($data['image']->getRealPath()),
            $data['image']->getMimeType() ?: 'image/png',
            $data['kind'] === 'motor',   // foto motor: biarkan Tripo merapikan foto dulu
        )]);
    }

    public function poll(string $task): JsonResponse
    {
        abort_unless((bool) preg_match('/^[A-Za-z0-9-]{8,64}$/', $task), 404);

        return $this->call(function () use ($task) {
            $result = $this->tripo->poll($task);
            if (isset($result['path'])) {
                $result['url'] = asset($result['path']);
            }

            return $result;
        });
    }

    private function call(callable $work): JsonResponse
    {
        if (! $this->tripo->enabled()) {
            return response()->json(['message' => 'Model 3D belum aktif: isi TRIPO_API_KEY di .env server.'], 503);
        }

        try {
            return response()->json($work());
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
