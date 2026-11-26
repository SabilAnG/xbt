<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\ProductionPlanner;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Pembelian bahan beserta pembayaran dan hutangnya. */
class MaterialPurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $query = MaterialPurchase::query()->with('items.material')
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('supplier', 'like', "%{$search}%")->orWhere('buyer', 'like', "%{$search}%")->orWhere('invoice_number', 'like', "%{$search}%")->orWhere('supplier_invoice', 'like', "%{$search}%")->orWhere('supplier_location', 'like', "%{$search}%")
            ))
            ->when($request->input('bayar') === 'hutang', fn ($query) => $query->unpaid())
            ->when($request->input('bayar') === 'jatuh-tempo', fn ($query) => $query->dueWithin()->orderBy('due_date'))
            ->when($request->input('terima') === 'belum', fn ($query) => $query->awaitingGoods())
            ->when($request->filled('supplier'), fn ($query) => $query->where('supplier_id', $request->integer('supplier')))
            ->latest('purchased_at')->latest('id');

        return view('admin.purchases.index', [
            'purchases' => $query->paginate(20)->withQueryString(),
            'payable' => (float) MaterialPurchase::query()->unpaid()->get()->sum(fn (MaterialPurchase $purchase) => $purchase->payable()),
            'awaiting' => MaterialPurchase::query()->awaitingGoods()->count(),
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(MaterialPurchase $purchase): View
    {
        return view('admin.purchases.show', [
            'purchase' => $purchase->load('items.material.warehouse', 'items.warehouse', 'payments.wallet', 'plan', 'supplierRecord', 'receipts.items.purchaseItem.material', 'receipts.items.warehouse', 'returns.items.purchaseItem.material', 'payments.deposit'),
        ]);
    }

    public function create(Request $request, ProductionPlanner $planner): View
    {
        $plan = $request->filled('rencana') ? ProductionPlan::findOrFail($request->integer('rencana')) : null;
        $lines = [];

        if ($plan) {
            $lines = $planner->forPlan($plan)['rows']->filter(fn (array $row) => $row['buy_qty'] > 0)->map(fn (array $row) => [
                'material_id' => $row['material']->id,
                'warehouse_id' => $row['material']->warehouse_id,
                'qty' => $row['buy_qty'],
                'bar_length_cm' => $row['material']->isPipe() ? (float) $row['material']->bar_length_cm : null,
                'unit_price' => round($row['material']->purchaseUnitCost(), 2),
            ])->values()->all();
        } elseif ($request->filled('material')) {
            $material = Material::findOrFail($request->integer('material'));
            $lines = [['material_id' => $material->id, 'warehouse_id' => $material->warehouse_id, 'qty' => 1, 'bar_length_cm' => $material->isPipe() ? (float) $material->bar_length_cm : null, 'unit_price' => (float) $material->last_price]];
        }

        return view('admin.purchases.create', [
            'plan' => $plan,
            'lines' => array_values(old('items', $lines ?: [['material_id' => '', 'warehouse_id' => '', 'qty' => '', 'bar_length_cm' => '', 'unit_price' => '']])),
            'materials' => Material::query()->active()->purchasable()->with('warehouse')->orderBy('name')->get(),
            'suppliers' => Supplier::query()->active()->orderBy('name')->pluck('address', 'name'),
            'buyers' => MaterialPurchase::query()->whereNotNull('buyer')->distinct()->orderBy('buyer')->pluck('buyer')
                ->merge(Production::query()->whereNotNull('worker')->distinct()->pluck('worker'))->unique()->values(),
            'warehouses' => collect([Warehouse::RAW, Warehouse::SEMI, Warehouse::ACCESSORY, Warehouse::OFFCUT])->mapWithKeys(fn (string $type) => [Warehouse::forType($type)->id => Warehouse::forType($type)->name]),
        ]);
    }

    public function store(Request $request, PurchaseService $service): RedirectResponse
    {
        $request->merge(['items' => collect($request->input('items', []))->filter(fn ($row) => filled($row['material_id'] ?? null))->values()->all()]);

        $data = $request->validate([
            'purchased_at' => ['required', 'date'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'supplier_location' => ['nullable', 'string', 'max:255'],
            'supplier_invoice' => ['nullable', 'string', 'max:100'],
            'due_date' => ['nullable', 'date', 'after_or_equal:purchased_at'],
            'buyer' => ['nullable', 'string', 'max:100'],
            'production_plan_id' => ['nullable', 'exists:production_plans,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'exists:materials,id'],
            'items.*.warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.bar_length_cm' => ['nullable', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'wallet_id' => ['nullable', 'exists:wallets,id'],
            'receive_now' => ['nullable', 'boolean'],
        ], [
            'items.required' => 'Isi minimal satu barang.',
            'items.*.qty.required' => 'Isi jumlah.',
            'items.*.unit_price.required' => 'Isi harga.',
        ]);

        foreach ($data['items'] as $i => $row) {
            $material = Material::find($row['material_id']);

            if ($material->isPipe() && floor((float) $row['qty']) != (float) $row['qty']) {
                return back()->withInput()->withErrors(["items.$i.qty" => 'Pipa dibeli per batang utuh.']);
            }

            if (filled($row['warehouse_id'] ?? null) && ! in_array((int) $row['warehouse_id'], $material->allowedWarehouseIds(), true)) {
                return back()->withInput()->withErrors(["items.$i.warehouse_id" => $material->isPipe()
                    ? 'Pipa hanya bisa masuk Gudang Bahan Baku atau Gudang Stok Sisa.'
                    : 'Bahan ini hanya bisa masuk Gudang Bahan Baku atau Gudang Setengah Jadi.']);
            }
        }

        if ((float) ($data['paid_amount'] ?? 0) > 0 && blank($data['wallet_id'] ?? null)) {
            return back()->withInput()->withErrors(['wallet_id' => 'Pilih dompet yang dipakai membayar.']);
        }

        $data['receive_now'] = $request->boolean('receive_now', true);

        try {
            $purchase = $service->create($data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchases.show', $purchase)->with('status', $data['receive_now'] ? 'Pembelian dicatat dan barang langsung diterima ke gudang.' : 'Pembelian dicatat. Terima barangnya lewat Penerimaan saat datang.');
    }

    public function destroy(MaterialPurchase $purchase, PurchaseService $service): RedirectResponse
    {
        try {
            $service->delete($purchase);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchases.index')->with('status', 'Pembelian dihapus; stok dan pembayarannya dibatalkan.');
    }

    public function cancelOutstanding(MaterialPurchase $purchase, PurchaseService $service): RedirectResponse
    {
        try {
            $service->cancelOutstanding($purchase);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Sisa pesanan dibatalkan; tagihan disesuaikan dengan barang yang diterima.');
    }

    public function storePayment(Request $request, MaterialPurchase $purchase, PurchaseService $service): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'wallet_id' => ['required', 'exists:wallets,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $service->pay($purchase, (float) $data['amount'], (int) $data['wallet_id'], $data['paid_at'], $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Pembayaran dicatat.');
    }

    public function storeReturn(Request $request, MaterialPurchase $purchase, PurchaseService $service): RedirectResponse
    {
        $data = $request->validate([
            'returned_at' => ['required', 'date'],
            'items' => ['required', 'array'],
            'items.*.material_purchase_item_id' => ['required', 'integer'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'wallet_id' => ['nullable', 'exists:wallets,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $data['items'] = collect($data['items'])->map(fn (array $row) => ['qty' => (float) ($row['qty'] ?? 0)] + $row)->all();

        try {
            $return = $service->returnGoods($purchase, $data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Retur '.$return->return_number.' dicatat: stok keluar, tagihan turun '.rupiah($return->total).'.');
    }

    public function destroyReturn(PurchaseReturn $return, PurchaseService $service): RedirectResponse
    {
        $service->deleteReturn($return);

        return back()->with('status', 'Retur dibatalkan; barang kembali ke gudang.');
    }

    public function destroyPayment(PurchasePayment $payment, PurchaseService $service): RedirectResponse
    {
        if (PurchaseReturn::where('purchase_payment_id', $payment->id)->exists()) {
            return back()->with('error', 'Ini uang kembali dari retur. Batalkan returnya bila perlu.');
        }

        $service->deletePayment($payment);

        return back()->with('status', 'Pembayaran dihapus.');
    }
}
