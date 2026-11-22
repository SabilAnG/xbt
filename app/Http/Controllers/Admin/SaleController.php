<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ListOption;
use App\Models\Sale;
use App\Services\InventoryService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** Penjualan: stok knalpot berkurang; qty minus = retur. */
class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $sales = Sale::query()->with(['items.item', 'wallet'])
            ->when($request->input('month'), fn ($query, $month) => $query->where('sold_at', 'like', "{$month}%"))
            ->when($request->input('channel'), fn ($query, $channel) => $query->where('channel', $channel))
            ->when($request->input('payment') === 'unpaid', fn ($query) => $query->whereColumn('paid_amount', '<', 'total'))
            ->latest('sold_at')->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.sales.index', ['sales' => $sales, 'channels' => ListOption::valuesFor('saluran')->all()]);
    }

    public function create(): View
    {
        return view('admin.sales.create', [
            'items' => Item::where('is_active', true)->orderBy('sku')->get(),
            'channels' => ListOption::valuesFor('saluran')->all(),
        ]);
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'sold_at' => ['required', 'date'],
            'item_id' => ['required', 'exists:items,id'],
            'qty' => ['required', 'numeric', 'not_in:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'channel' => ['nullable', Rule::in(ListOption::valuesFor('saluran')->all())],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'wallet_id' => ['nullable', 'exists:wallets,id'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $sale = $inventory->recordSale(Item::findOrFail($data['item_id']), (float) $data['qty'], (float) $data['unit_price'], $data['sold_at'], $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['qty' => $e->getMessage()]);
        }

        $this->syncWallet($sale);

        return redirect()->route('admin.sales.index')->with('status', 'Penjualan dicatat. Stok knalpot berkurang.');
    }

    public function edit(Sale $sale): View
    {
        return view('admin.sales.edit', ['sale' => $sale->load('items.item'), 'channels' => ListOption::valuesFor('saluran')->all()]);
    }

    /** Hanya data pembayaran & pembeli yang bisa diubah; qty/harga = hapus lalu input ulang. */
    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $sale->update($request->validate([
            'wallet_id' => ['nullable', 'exists:wallets,id'],
            'channel' => ['nullable', Rule::in(ListOption::valuesFor('saluran')->all())],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]));

        $this->syncWallet($sale->refresh());

        return redirect()->route('admin.sales.index')->with('status', 'Penjualan diperbarui.');
    }

    /** Uang yang sudah dibayar pembeli masuk ke dompet yang dipilih. */
    private function syncWallet(Sale $sale): void
    {
        $item = $sale->items()->with('item')->first()?->item;

        app(WalletService::class)->sync(
            $sale, $sale->wallet_id, 'in', (float) $sale->paid_amount, $sale->sold_at,
            'Penjualan '.$sale->invoice_number.($item ? ' — '.$item->name : '').($sale->customer_name ? ' ('.$sale->customer_name.')' : ''),
        );
    }

    public function destroy(Sale $sale, InventoryService $inventory): RedirectResponse
    {
        $inventory->deleteSale($sale);

        return redirect()->route('admin.sales.index')->with('status', 'Penjualan dihapus dan stok dikembalikan.');
    }
}
