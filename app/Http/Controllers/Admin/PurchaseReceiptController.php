<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialPurchase;
use App\Models\PipePiece;
use App\Models\PurchaseReceipt;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Penerimaan barang dari pembelian, seperti di EMS: yang dipesan dan yang
 * datang dicatat terpisah. Stok gudang bertambah saat penerimaan disimpan.
 */
class PurchaseReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $receipts = PurchaseReceipt::query()->with('purchase', 'items.purchaseItem.material')
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('receipt_number', 'like', "%{$search}%")->orWhere('received_by', 'like', "%{$search}%")
                    ->orWhereHas('purchase', fn ($query) => $query->where('invoice_number', 'like', "%{$search}%")->orWhere('supplier', 'like', "%{$search}%"))
            ))
            ->latest('received_at')->latest('id');

        return view('admin.receipts.index', [
            'receipts' => $receipts->paginate(20)->withQueryString(),
            'awaiting' => MaterialPurchase::query()->awaitingGoods()->with('items.material')->oldest('purchased_at')->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $purchase = MaterialPurchase::query()->with('items.material.warehouse', 'items.warehouse')->findOrFail($request->integer('pembelian'));

        if ($purchase->isService() || $purchase->outstandingQty() <= 0) {
            return redirect()->route('admin.purchases.show', $purchase)->with('error', 'Semua barang di pembelian ini sudah diterima.');
        }

        return view('admin.receipts.create', [
            'purchase' => $purchase,
            'lines' => $purchase->items->filter(fn ($line) => $line->outstandingQty() > 0)->values(),
            'warehouses' => collect([Warehouse::RAW, Warehouse::SEMI, Warehouse::ACCESSORY, Warehouse::OFFCUT])->mapWithKeys(fn (string $type) => [Warehouse::forType($type)->id => Warehouse::forType($type)->name]),
        ]);
    }

    public function store(Request $request, PurchaseService $service): RedirectResponse
    {
        $data = $request->validate([
            'material_purchase_id' => ['required', 'exists:material_purchases,id'],
            'received_at' => ['required', 'date'],
            'received_by' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_purchase_item_id' => ['required', 'exists:material_purchase_items,id'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.warehouse_id' => ['nullable', 'exists:warehouses,id'],
        ]);

        $purchase = MaterialPurchase::findOrFail($data['material_purchase_id']);
        $data['items'] = collect($data['items'])->map(fn (array $row) => ['qty' => (float) ($row['qty'] ?? 0)] + $row)->all();

        try {
            $receipt = $service->receive($purchase, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.receipts.show', $receipt)->with('status', 'Barang diterima dan masuk gudang.');
    }

    public function show(PurchaseReceipt $receipt): View
    {
        return view('admin.receipts.show', [
            'receipt' => $receipt->load('purchase', 'items.purchaseItem.material', 'items.warehouse'),
            'pieces' => PipePiece::query()->where('source_type', $receipt->getMorphClass())->where('source_id', $receipt->id)->get(),
        ]);
    }

    public function destroy(PurchaseReceipt $receipt, PurchaseService $service): RedirectResponse
    {
        $purchase = $receipt->purchase;

        try {
            $service->deleteReceipt($receipt);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchases.show', $purchase)->with('status', 'Penerimaan dihapus; stoknya ditarik kembali.');
    }
}
