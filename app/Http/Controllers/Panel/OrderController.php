<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\PelacakanDhl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Order pembeli di panel baru.
 *
 * Inilah yang menyuapi halaman /tracking di situs publik: nomor order dicari
 * pembeli, lalu pengiriman dan linimasanya yang ditampilkan. Karena itu nomor
 * resi dan nama kurir di sini bukan catatan internal — keduanya langsung
 * terbaca orang luar.
 *
 * Kurir yang memuat kata "dhl" disegarkan otomatis dari MyDHL API saat halaman
 * tracking dibuka; lihat App\Services\PelacakanDhl.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));
        $status = in_array($request->query('status'), array_keys(Order::STATUSES), true)
            ? $request->query('status')
            : null;

        return view('panel.order.index', [
            'order' => Order::query()
                ->withCount('shipments')
                ->when($cari !== '', fn ($q) => $q->where(
                    fn ($q) => $q->where('order_number', 'like', "%{$cari}%")
                        ->orWhere('customer_name', 'like', "%{$cari}%")
                        ->orWhere('customer_phone', 'like', "%{$cari}%")
                ))
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'cari' => $cari,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('panel.order.form', ['order' => new Order]);
    }

    public function edit(Order $order): View
    {
        $order->load('shipments.events');

        return view('panel.order.form', ['order' => $order]);
    }

    public function store(Request $request): RedirectResponse
    {
        $order = Order::create($this->bersihkan($request));

        return redirect()->route('panel.order.edit', $order)
            ->with('sukses', "Order {$order->order_number} dibuat. Pengirimannya bisa ditambahkan di sini.");
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $order->update($this->bersihkan($request, $order));

        return redirect()->route('panel.order.index')
            ->with('sukses', "Order {$order->order_number} disimpan.");
    }

    public function destroy(Order $order): RedirectResponse
    {
        $nomor = $order->order_number;
        $order->delete();

        return redirect()->route('panel.order.index')
            ->with('sukses', "Order {$nomor} dihapus.");
    }

    /** Tambah satu pengiriman ke order. */
    public function tambahKiriman(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:255'],
            'tracking_number' => ['required', 'string', 'max:128'],
            'status' => ['nullable', 'string', 'max:255'],
        ], [], ['provider' => 'kurir', 'tracking_number' => 'nomor resi']);

        $order->shipments()->create($data + [
            'sort_order' => (int) $order->shipments()->max('sort_order') + 1,
        ]);

        return back()->with('sukses', 'Pengiriman ditambahkan.');
    }

    public function hapusKiriman(Order $order, Shipment $kiriman): RedirectResponse
    {
        // Id kiriman datang dari alamat; tanpa pemeriksaan ini pengiriman milik
        // order lain bisa dihapus lewat order ini.
        abort_unless($kiriman->order_id === $order->id, 404);

        $kiriman->delete();

        return back()->with('sukses', 'Pengiriman dihapus.');
    }

    /**
     * Tarik status terbaru dari DHL sekarang juga.
     *
     * Halaman /tracking menyegarkan sendiri dengan cache 15 menit; tombol ini
     * untuk saat orang sedang menelepon dan butuh jawaban detik itu. Cache-nya
     * dilangkahi dengan mengosongkan `last_checked`.
     */
    public function segarkanDhl(Order $order, PelacakanDhl $dhl): RedirectResponse
    {
        if (! $dhl->aktif()) {
            return back()->with('gagal', 'Kredensial DHL belum diisi di .env, jadi tidak ada yang bisa ditarik.');
        }

        $order->shipments()
            ->get()
            ->filter(fn (Shipment $k) => $k->pakaiDhl())
            ->each(function (Shipment $k) use ($dhl) {
                $k->forceFill(['last_checked' => null])->save();
                $dhl->segarkan($k);
            });

        return back()->with('sukses', 'Status DHL ditarik ulang.');
    }

    // ------------------------------------------------------------- internal

    /** @return array<string, mixed> */
    private function bersihkan(Request $request, ?Order $abaikan = null): array
    {
        return $request->validate([
            'order_number' => ['required', 'string', 'max:64', Rule::unique('orders', 'order_number')->ignore($abaikan)],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'notes' => ['nullable', 'string'],
        ], [], ['order_number' => 'nomor order']);
    }
}
