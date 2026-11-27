<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fitur AI Simulasi Desain lewat OpenAI (key hanya di server, .env OPENAI_API_KEY):
 * - locateExhaustMount(): cari roda belakang & arah motor di foto (untuk pasang otomatis)
 * - readReference(): baca bentuk, proporsi & finishing knalpot di foto contoh
 * - render(): foto motor + gambar knalpot → foto baru knalpot terpasang (foto-realistis)
 */
class DesignAi
{
    private const BASE = 'https://api.openai.com/v1';

    public function enabled(): bool
    {
        return filled(config('services.openai.key'));
    }

    /**
     * @return array{rear_wheel: array{x: float, y: float, r: float}, facing: string, confidence: float}
     */
    public function locateExhaustMount(string $image, string $mime): array
    {
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['found', 'rear_wheel', 'facing', 'confidence'],
            'properties' => [
                'found' => ['type' => 'boolean', 'description' => 'true bila ada motor dengan roda belakang terlihat'],
                'rear_wheel' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['x', 'y', 'r'],
                    'properties' => [
                        'x' => ['type' => 'number', 'description' => 'pusat as roda belakang, 0–1 dari lebar foto'],
                        'y' => ['type' => 'number', 'description' => 'pusat as roda belakang, 0–1 dari tinggi foto'],
                        'r' => ['type' => 'number', 'description' => 'jari-jari LUAR ban belakang (tinggi ban ÷ 2), 0–1 dari LEBAR foto'],
                    ],
                ],
                'facing' => ['type' => 'string', 'enum' => ['left', 'right'], 'description' => 'arah depan motor di foto'],
                'confidence' => ['type' => 'number'],
            ],
        ];

        $result = $this->vision(
            'Kamu ahli foto motor. Tentukan posisi roda BELAKANG dan arah depan motor di foto ini. '
            .'Koordinat dinormalisasi: x dan r dibagi lebar foto, y dibagi tinggi foto. '
            .'Untuk foto sudut 3/4, r = setengah TINGGI ban belakang (tidak terdistorsi perspektif). '
            .'Roda belakang adalah roda di sisi mesin/CVT dan bawah jok; roda depan di bawah setang dan lampu.',
            $image, $mime, 'lokasi_roda', $schema,
        );

        if (! ($result['found'] ?? false)) {
            throw new RuntimeException('AI tidak menemukan motor atau roda belakang di foto ini.');
        }

        $wheel = $result['rear_wheel'];

        return [
            'rear_wheel' => ['x' => $this->clamp($wheel['x']), 'y' => $this->clamp($wheel['y']), 'r' => max(0.01, min(0.5, (float) $wheel['r']))],
            'facing' => $result['facing'] === 'left' ? 'left' : 'right',
            'confidence' => $this->clamp($result['confidence'] ?? 0.5),
        ];
    }

    /**
     * @return array{shape: string, finish: string, diameter_ratio: float, tip: string, silencer_share: float, summary: string}
     */
    public function readReference(string $image, string $mime): array
    {
        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['is_exhaust', 'shape', 'finish', 'diameter_ratio', 'tip', 'silencer_share', 'summary'],
            'properties' => [
                'is_exhaust' => ['type' => 'boolean'],
                'shape' => ['type' => 'string', 'enum' => ['gp', 'round', 'oval', 'hexagon', 'megaphone'], 'description' => 'gp = penampang trapesium/segi banyak pipih ala silincer racing modern'],
                'finish' => ['type' => 'string', 'enum' => ['satin', 'stainless', 'burnt', 'black', 'carbon'], 'description' => 'material/warna badan silincer'],
                'diameter_ratio' => ['type' => 'number', 'description' => 'tinggi/diameter badan silincer ÷ panjang silincer (tanpa leheran), dikoreksi sudut foto'],
                'tip' => ['type' => 'string', 'enum' => ['straight', 'slash', 'double', 'cap']],
                'silencer_share' => ['type' => 'number', 'description' => 'panjang silincer ÷ panjang seluruh knalpot yang terlihat (0–1)'],
                'summary' => ['type' => 'string', 'description' => 'ringkasan 1 kalimat bahasa Indonesia'],
            ],
        ];

        $result = $this->vision(
            'Kamu insinyur knalpot motor. Baca knalpot di foto ini: bentuk penampang silincer, material/finishing, '
            .'proporsi diameter terhadap panjang silincer (koreksi perspektif), model ujung, dan porsi silincer dari seluruh knalpot.',
            $image, $mime, 'baca_knalpot', $schema,
        );

        if (! ($result['is_exhaust'] ?? false)) {
            throw new RuntimeException('AI tidak mengenali knalpot di foto ini.');
        }

        return [
            'shape' => $result['shape'],
            'finish' => $result['finish'],
            'diameter_ratio' => max(0.15, min(0.6, (float) $result['diameter_ratio'])),
            'tip' => $result['tip'],
            'silencer_share' => max(0.2, min(1, (float) $result['silencer_share'])),
            'summary' => (string) $result['summary'],
        ];
    }

    /**
     * Foto motor + gambar knalpot (desain) → foto baru dengan knalpot terpasang.
     *
     * @return string PNG biner
     */
    public function render(string $motorPhoto, string $motorMime, string $exhaust, string $exhaustMime, string $motorName, string $placementHint): string
    {
        $prompt = 'Foto 1 adalah motor '.$motorName.'. Foto 2 adalah knalpot racing baru (desain). '
            .'Edit foto 1: ganti knalpot standar motor dengan knalpot dari foto 2, terpasang realistis di sisi knalpot motor, '
            .'leheran tersambung ke lubang buang mesin dan silincer di samping roda belakang. '
            .'Pertahankan bentuk, warna, finishing, stiker dan proporsi knalpot dari foto 2 setepat mungkin. '
            .'Jangan mengubah motor, latar, sudut kamera, warna bodi atau komposisi foto 1. '
            .'Cahaya, bayangan dan pantulan knalpot harus menyatu dengan foto 1 sehingga tampak seperti foto asli. '
            .$placementHint;

        try {
            $response = Http::withToken((string) config('services.openai.key'))
                ->timeout(240)
                ->asMultipart()
                ->attach('image[]', $motorPhoto, 'motor.'.$this->extension($motorMime), ['Content-Type' => $motorMime])
                ->attach('image[]', $exhaust, 'knalpot.'.$this->extension($exhaustMime), ['Content-Type' => $exhaustMime])
                ->post(self::BASE.'/images/edits', [
                    'model' => config('services.openai.image_model'),
                    'prompt' => $prompt,
                    'size' => 'auto',
                    'quality' => config('services.openai.image_quality'),
                    'n' => 1,
                ])
                ->throw();
        } catch (ConnectionException $e) {
            throw new RuntimeException('Tidak bisa terhubung ke OpenAI. Coba lagi sebentar.', 0, $e);
        } catch (RequestException $e) {
            throw new RuntimeException('OpenAI menolak permintaan render: '.$this->apiError($e), 0, $e);
        }

        $b64 = $response->json('data.0.b64_json');
        if (! is_string($b64) || ($png = base64_decode($b64, true)) === false) {
            throw new RuntimeException('OpenAI tidak mengirim gambar hasil render.');
        }

        return $png;
    }

    /**
     * Satu panggilan vision dengan keluaran JSON terstruktur.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function vision(string $instruction, string $image, string $mime, string $name, array $schema): array
    {
        try {
            $response = Http::withToken((string) config('services.openai.key'))
                ->timeout(120)
                ->post(self::BASE.'/chat/completions', [
                    'model' => config('services.openai.vision_model'),
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $instruction],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$mime.';base64,'.base64_encode($image)]],
                        ],
                    ]],
                    'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => $name, 'strict' => true, 'schema' => $schema]],
                ])
                ->throw();
        } catch (ConnectionException $e) {
            throw new RuntimeException('Tidak bisa terhubung ke OpenAI. Coba lagi sebentar.', 0, $e);
        } catch (RequestException $e) {
            throw new RuntimeException('OpenAI menolak permintaan: '.$this->apiError($e), 0, $e);
        }

        $json = json_decode((string) $response->json('choices.0.message.content'), true);
        if (! is_array($json)) {
            throw new RuntimeException('Jawaban AI tidak bisa dibaca.');
        }

        return $json;
    }

    private function apiError(RequestException $e): string
    {
        return (string) ($e->response->json('error.message') ?? 'HTTP '.$e->response->status());
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }

    private function clamp(mixed $value): float
    {
        return max(0.0, min(1.0, (float) $value));
    }
}
