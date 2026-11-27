<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Foto → model 3D (GLB) lewat Tripo3D API v3 (developers.tripo3d.ai).
 * Key hanya di server (.env TRIPO_API_KEY). Hasil diunduh ke server begitu jadi
 * karena URL model dari Tripo hanya berlaku ±5 menit.
 */
class Tripo3d
{
    private const BASE = 'https://openapi.tripo3d.ai/v3';

    public const DIRECTORY = 'uploads/desain/3d';

    public function enabled(): bool
    {
        return filled(config('services.tripo.key'));
    }

    /** Unggah foto lalu mulai pembuatan model; kembalikan task_id. */
    public function start(string $image, string $mime, bool $autofix): string
    {
        $upload = $this->send(fn ($http) => $http
            ->attach('file', $image, 'foto.'.($mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/webp' ? 'webp' : 'png')), ['Content-Type' => $mime])
            ->post(self::BASE.'/files'));
        $token = $upload['data']['file_token'] ?? null;
        if (! is_string($token)) {
            throw new RuntimeException('Tripo3D tidak mengembalikan token file.');
        }

        $task = $this->send(fn ($http) => $http->asJson()->post(self::BASE.'/generation/image-to-model', [
            'input' => $token,
            'model' => config('services.tripo.model'),
            'texture' => true,
            'pbr' => true,
            'texture_quality' => config('services.tripo.texture_quality'),
            'enable_image_autofix' => $autofix,
        ]));
        $taskId = $task['data']['task_id'] ?? null;
        if (! is_string($taskId)) {
            throw new RuntimeException('Tripo3D tidak mengembalikan task_id.');
        }

        return $taskId;
    }

    /**
     * Status pembuatan; saat sukses GLB diunduh ke server (sekali saja).
     *
     * @return array{status: string, progress: int, path?: string}
     */
    public function poll(string $taskId): array
    {
        $path = self::DIRECTORY.'/'.$taskId.'.glb';
        if (Storage::disk('site')->exists($path)) {
            return ['status' => 'success', 'progress' => 100, 'path' => $path];
        }

        $data = $this->send(fn ($http) => $http->get(self::BASE.'/tasks/'.$taskId))['data'] ?? [];
        $status = (string) ($data['status'] ?? 'unknown');

        if ($status === 'success') {
            $url = $data['output']['model_url'] ?? null;
            if (! is_string($url)) {
                throw new RuntimeException('Model 3D selesai tetapi tautan file tidak ada.');
            }
            try {
                $glb = Http::timeout(180)->get($url)->throw()->body();
            } catch (ConnectionException|RequestException $e) {
                throw new RuntimeException('Gagal mengunduh model 3D dari Tripo3D (tautan berlaku 5 menit).', 0, $e);
            }
            if (! str_starts_with($glb, 'glTF')) {
                throw new RuntimeException('File dari Tripo3D bukan GLB.');
            }
            Storage::disk('site')->put($path, $glb);

            return ['status' => 'success', 'progress' => 100, 'path' => $path];
        }

        if (in_array($status, ['failed', 'cancelled', 'banned', 'expired'], true)) {
            throw new RuntimeException('Pembuatan model 3D gagal: '.($data['error_message'] ?? $status).'.');
        }

        return ['status' => $status, 'progress' => (int) ($data['progress'] ?? 0)];
    }

    /** @return array<string, mixed> */
    private function send(callable $request): array
    {
        try {
            $response = $request(Http::withToken((string) config('services.tripo.key'))->timeout(120))->throw();
        } catch (ConnectionException $e) {
            throw new RuntimeException('Tidak bisa terhubung ke Tripo3D. Coba lagi sebentar.', 0, $e);
        } catch (RequestException $e) {
            $code = $e->response->json('code');
            $message = match ((int) $code) {
                2, 1002 => 'API key Tripo3D salah atau belum aktif.',
                2010 => 'Kredit Tripo3D tidak cukup. Isi ulang di developers.tripo3d.ai.',
                2008 => 'Foto ditolak kebijakan konten Tripo3D.',
                1007, 2000 => 'Tripo3D sedang sibuk / batas permintaan tercapai. Coba lagi sebentar.',
                default => (string) ($e->response->json('message') ?? 'HTTP '.$e->response->status()),
            };

            throw new RuntimeException('Tripo3D: '.$message, 0, $e);
        }

        if ((int) $response->json('code', 0) !== 0) {
            throw new RuntimeException('Tripo3D: '.($response->json('message') ?? 'permintaan gagal').'.');
        }

        return $response->json();
    }
}
