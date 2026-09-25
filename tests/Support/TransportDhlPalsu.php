<?php

namespace Tests\Support;

use Ihc\DhlGate\Contracts\Transport;
use Ihc\DhlGate\Http\Request;
use Ihc\DhlGate\Http\Response;

/**
 * Transport DHL palsu.
 *
 * Seluruh tes pelacakan memakai ini, jadi tidak ada satu pun panggilan jaringan
 * dari suite — kredensial DHL pun tidak diperlukan untuk menjalankannya.
 * Jumlah panggilan dihitung karena itulah yang membuktikan cache bekerja.
 */
class TransportDhlPalsu implements Transport
{
    public int $panggilan = 0;

    public function __construct(
        private readonly int $status = 200,
        private readonly string $body = '{"shipments":[]}',
    ) {}

    /** Jawaban DHL untuk satu resi yang sudah terkirim. */
    public static function terkirim(string $resi = '1234567890'): self
    {
        return new self(200, json_encode([
            'shipments' => [[
                'shipmentTrackingNumber' => $resi,
                'status' => 'Delivered',
                'description' => 'Delivered - Signed for by: BUDI',
                'events' => [
                    [
                        'date' => '2026-09-19',
                        'time' => '10:05:00',
                        'GMTOffset' => '+07:00',
                        'typeCode' => 'OK',
                        'description' => 'Delivered - Signed for by: BUDI',
                        'serviceArea' => [['code' => 'JKT', 'description' => 'Jakarta - Indonesia']],
                        'signedBy' => 'BUDI',
                    ],
                    [
                        'date' => '2026-09-18',
                        'time' => '07:30:00',
                        'GMTOffset' => '+07:00',
                        'typeCode' => 'PU',
                        'description' => 'Shipment picked up',
                        'serviceArea' => [['code' => 'SUB', 'description' => 'Surabaya - Indonesia']],
                    ],
                ],
            ]],
        ], JSON_THROW_ON_ERROR));
    }

    /** DHL sedang bermasalah. */
    public static function rusak(): self
    {
        return new self(500, '{"detail":"Internal Server Error"}');
    }

    public function send(Request $request, string $baseUrl, array $headers): Response
    {
        $this->panggilan++;

        return new Response($this->status, $this->body, ['content-type' => 'application/json']);
    }
}
