<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pesanan & pengiriman yang dijawab halaman /tracking. Tiap pengiriman jadi
 * satu tab di halaman tracking; riwayatnya tampil terbaru di atas.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()->with('sale')->withCount('shipments')
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhereHas('sale', fn ($query) => $query->where('invoice_number', 'like', "%{$search}%"))
                    ->orWhereHas('shipments', fn ($query) => $query->where('tracking_number', 'like', "%{$search}%"))
            ))
            ->latest()
            ->paginate(25)->withQueryString();

        return view('admin.website.orders.index', ['orders' => $orders, 'statuses' => Order::STATUSES]);
    }

    /** Dari nota penjualan (?penjualan=ID): nomor & data pembeli terisi otomatis. */
    public function create(Request $request): View|RedirectResponse
    {
        $order = new Order(['status' => 'processing']);

        if ($sale = Sale::find($request->integer('penjualan'))) {
            if ($sale->order) {
                return redirect()->route('admin.orders.edit', $sale->order)->with('status', 'Nota ini sudah punya pesanan/tracking.');
            }

            $order->fill(['sale_id' => $sale->id, 'order_number' => $sale->invoice_number, 'customer_name' => $sale->customer_name, 'customer_phone' => $sale->customer_phone]);
        }

        return view('admin.website.orders.form', ['order' => $order, 'statuses' => Order::STATUSES, 'sales' => $this->saleOptions($order)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $order = Order::create($this->validated($request));

        return redirect()->route('admin.orders.edit', $order)->with('status', 'Pesanan dibuat. Tambahkan pengiriman di bawah.');
    }

    public function edit(Order $order): View
    {
        return view('admin.website.orders.form', [
            'order' => $order->load('shipments.events', 'sale'),
            'statuses' => Order::STATUSES,
            'sales' => $this->saleOptions($order),
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $order->update($this->validated($request, $order));

        return redirect()->route('admin.orders.edit', $order)->with('status', 'Pesanan disimpan.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $order->delete();

        return redirect()->route('admin.orders.index')->with('status', 'Pesanan dihapus.');
    }

    public function storeShipment(Request $request, Order $order): RedirectResponse
    {
        $order->shipments()->create([
            ...$this->validatedShipment($request),
            'sort_order' => (int) $order->shipments()->max('sort_order') + 1,
        ]);

        return redirect()->route('admin.orders.edit', $order)->with('status', 'Pengiriman ditambahkan.');
    }

    public function updateShipment(Request $request, Shipment $shipment): RedirectResponse
    {
        $shipment->update($this->validatedShipment($request));

        return redirect()->route('admin.orders.edit', $shipment->order_id)->with('status', 'Pengiriman disimpan.');
    }

    public function destroyShipment(Shipment $shipment): RedirectResponse
    {
        $shipment->delete();

        return redirect()->route('admin.orders.edit', $shipment->order_id)->with('status', 'Pengiriman dihapus.');
    }

    public function storeEvent(Request $request, Shipment $shipment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:128'],
            'happened_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $shipment->events()->create($data);

        return redirect()->route('admin.orders.edit', $shipment->order_id)->with('status', 'Riwayat tracking ditambahkan.');
    }

    public function destroyEvent(ShipmentEvent $event): RedirectResponse
    {
        $orderId = $event->shipment->order_id;
        $event->delete();

        return redirect()->route('admin.orders.edit', $orderId)->with('status', 'Riwayat tracking dihapus.');
    }

    /**
     * Nota penjualan yang belum punya pesanan (ditambah nota pesanan ini sendiri).
     *
     * @return Collection<int, string>
     */
    private function saleOptions(Order $order): Collection
    {
        return Sale::query()->with('items.item')->latest('sold_at')->latest('id')->limit(200)
            ->where(fn ($query) => $query->whereDoesntHave('order')->orWhere('id', $order->sale_id))
            ->get()->mapWithKeys(fn (Sale $sale) => [$sale->id => $sale->invoice_number.' · '.$sale->sold_at->format('d/m').' · '.($sale->customer_name ?: 'tanpa nama').' · '.$sale->itemSummary()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Order $order = null): array
    {
        return $request->validate([
            'sale_id' => ['nullable', 'exists:sales,id', Rule::unique('orders', 'sale_id')->ignore($order)],
            'order_number' => ['required', 'string', 'max:64', Rule::unique('orders', 'order_number')->ignore($order)],
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedShipment(Request $request): array
    {
        return $request->validate([
            'tracking_number' => ['required', 'string', 'max:128'],
            'provider' => ['nullable', 'string', 'max:128'],
            'status' => ['required', 'string', 'max:64'],
            'status_description' => ['nullable', 'string', 'max:255'],
            'status_updated_at' => ['nullable', 'date'],
            'last_checked' => ['nullable', 'date'],
        ]);
    }
}
