<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Penjualan: satu nota bisa berisi beberapa produk; stok knalpot berkurang
 * (qty minus = retur). Uang masuk dicatat sebagai pembayaran, jadi piutang
 * bisa dilunasi bertahap.
 */
class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $sales = Sale::query()->with(['items.item', 'payments.wallet', 'order'])
            ->when($request->input('month'), fn ($query, $month) => $query->where('sold_at', 'like', "{$month}%"))
            ->when($request->input('channel'), fn ($query, $channel) => $query->where('channel', $channel))
            ->when($request->input('payment') === 'unpaid', fn ($query) => $query->whereColumn('paid_amount', '<', 'total'))
            ->when($request->input('payment') === 'refund', fn ($query) => $query->whereColumn('paid_amount', '>', 'total'))
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('invoice_number', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhereHas('items.item', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ))
            ->latest('sold_at')->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.sales.index', ['sales' => $sales, 'channels' => Sale::CHANNELS]);
    }

    public function create(): View
    {
        return view('admin.sales.create', [
            'items' => Item::where('is_active', true)->orderBy('sku')->get(),
            'channels' => Sale::CHANNELS,
        ]);
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        // Form lama (satu produk) tetap diterima.
        if (! $request->has('lines') && $request->filled('item_id')) {
            $request->merge(['lines' => [$request->only('item_id', 'qty', 'unit_price')]]);
        }

        $data = $request->validate([
            'sold_at' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'exists:items,id'],
            'lines.*.qty' => ['required', 'numeric', 'not_in:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'channel' => ['nullable', Rule::in(Sale::CHANNELS)],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:64'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'wallet_id' => ['nullable', 'required_with:paid_amount', 'exists:wallets,id'],
            'notes' => ['nullable', 'string'],
        ], [
            'lines.required' => 'Tambahkan minimal satu produk.',
            'lines.*.item_id.required' => 'Pilih produknya.',
            'lines.*.qty.not_in' => 'Qty tidak boleh 0.',
            'wallet_id.required_with' => 'Pilih dompet tujuan uang masuk.',
        ]);

        try {
            $sale = DB::transaction(function () use ($inventory, $data) {
                $sale = $inventory->createSale($data['lines'], $data['sold_at'], $data);

                if ((float) ($data['paid_amount'] ?? 0) > 0) {
                    $inventory->paySale($sale, min((float) $data['paid_amount'], max((float) $sale->total, 0)), (int) $data['wallet_id'], $data['sold_at'], 'Dibayar saat transaksi');
                }

                return $sale;
            });
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()->route('admin.sales.show', $sale)->with('status', 'Penjualan dicatat. Stok knalpot berkurang.');
    }

    public function show(Sale $sale): View
    {
        return view('admin.sales.show', ['sale' => $sale->load('items.item', 'payments.wallet', 'order.shipments')]);
    }

    public function edit(Sale $sale): View
    {
        return view('admin.sales.edit', ['sale' => $sale->load('items.item'), 'channels' => Sale::CHANNELS]);
    }

    /** Hanya data pembeli yang bisa diubah; produk/harga = hapus lalu input ulang, uang masuk lewat pembayaran. */
    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $sale->update($request->validate([
            'channel' => ['nullable', Rule::in(Sale::CHANNELS)],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
        ]));

        return redirect()->route('admin.sales.show', $sale)->with('status', 'Penjualan diperbarui.');
    }

    public function destroy(Sale $sale, InventoryService $inventory): RedirectResponse
    {
        $inventory->deleteSale($sale);

        return redirect()->route('admin.sales.index')->with('status', 'Penjualan dihapus; stok dan uang masuknya dikembalikan.');
    }

    public function storePayment(Request $request, Sale $sale, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'wallet_id' => ['required', 'exists:wallets,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $inventory->paySale($sale, (float) $data['amount'], (int) $data['wallet_id'], $data['paid_at'], $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', $sale->fresh()->isPaidOff() ? 'Pembayaran dicatat. Nota lunas.' : 'Pembayaran dicatat.');
    }

    public function storeRefund(Request $request, Sale $sale, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'wallet_id' => ['required', 'exists:wallets,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $inventory->refundSale($sale, (float) $data['amount'], (int) $data['wallet_id'], $data['paid_at'], $data['notes'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Uang kembali ke pembeli dicatat.');
    }

    public function destroyPayment(SalePayment $payment, InventoryService $inventory): RedirectResponse
    {
        $inventory->deleteSalePayment($payment);

        return back()->with('status', 'Pembayaran dihapus; saldo dompet dikembalikan.');
    }
}
