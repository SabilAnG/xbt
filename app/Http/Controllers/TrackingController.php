<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PelacakanDhl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /**
     * Order lookup behind the form on /tracking.
     *
     * Response shape is the one the page's JavaScript reads:
     *   { status, message, data: { order_ref_number, shipments: [...] } }
     * Each shipment carries provider, tracking_number, status,
     * status_description, status_updated_at, last_checked and events[].
     */
    public function track(Request $request, PelacakanDhl $dhl): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:64'],
        ]);

        $orderNumber = trim($validated['order_number']);

        $order = Order::with(['shipments.events'])
            ->whereRaw('LOWER(order_number) = ?', [mb_strtolower($orderNumber)])
            ->first();

        if (! $order) {
            return response()->json([
                'status' => false,
                'message' => "Order {$orderNumber} was not found. Please check the number and try again.",
                'data' => [
                    'order_ref_number' => $orderNumber,
                    'shipments' => [],
                ],
            ], 404);
        }

        // Kiriman DHL ditanyakan langsung ke DHL, dibatasi cache di dalam
        // layanannya sendiri. Kegagalan apa pun ditelan di sana: halaman ini
        // tetap menjawab dengan apa yang sudah tersimpan, karena orang yang
        // menunggu paket lebih baik melihat status kemarin daripada error.
        $dhl->segarkanSemua($order->shipments);

        // Dimuat ulang karena penyegaran tadi menulis ke baris yang sudah
        // terlanjur ada di memori — tanpa ini jawabannya masih versi lama.
        $order->load(['shipments.events']);

        return response()->json([
            'status' => true,
            'message' => 'Order found.',
            'data' => $order->toTrackingPayload(),
        ]);
    }
}
