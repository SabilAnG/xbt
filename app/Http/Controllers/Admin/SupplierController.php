<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Formula;
use App\Models\FormulaComponent;
use App\Models\MaterialPurchase;
use App\Models\Production;
use App\Models\Supplier;
use App\Models\SupplierDeposit;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Master Supplier: toko bahan dan vendor jasa, beserta hutang ke masing-masing. */
class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->withCount('purchases')
            ->withSum('purchases as purchases_total', 'total')
            ->withSum('purchases as purchases_paid', 'paid_amount')
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('address', 'like', "%{$search}%")
            ))
            ->orderByDesc('is_active')->orderBy('name')
            ->get();

        return view('admin.suppliers.index', ['suppliers' => $suppliers]);
    }

    public function show(Supplier $supplier): View
    {
        return view('admin.suppliers.show', [
            'supplier' => $supplier,
            'purchases' => $supplier->purchases()->with('items.material')->latest('purchased_at')->latest('id')->paginate(20),
            'payable' => $supplier->payable(),
            'total' => (float) $supplier->purchases()->sum('total'),
            'awaiting' => $supplier->purchases()->awaitingGoods()->count(),
            'vendorJobs' => Production::query()->where('vendor', $supplier->name)->count(),
            'unpaid' => $supplier->purchases()->unpaid()->oldest('purchased_at')->oldest('id')->get(),
            'deposits' => $supplier->deposits()->with('wallet')->withSum('payments as used', 'amount')->get(),
            'depositBalance' => $supplier->depositBalance(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validated($request) + ['is_active' => true]);

        return redirect()->route('admin.suppliers.show', $supplier)->with('status', 'Supplier ditambahkan.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $this->validated($request, $supplier) + ['is_active' => $request->boolean('is_active')];
        $oldName = $supplier->name;
        $supplier->update($data);

        // Nama di nota & produksi vendor ikut diperbarui supaya tetap nyambung.
        if ($oldName !== $supplier->name) {
            MaterialPurchase::where('supplier_id', $supplier->id)->update(['supplier' => $supplier->name]);
            Production::where('vendor', $oldName)->update(['vendor' => $supplier->name]);
            // Nama vendor yang tersalin di formula (komponen dijasakan & formula setengah jadi).
            FormulaComponent::where('vendor', $oldName)->update(['vendor' => $supplier->name]);
            Formula::where('vendor', $oldName)->update(['vendor' => $supplier->name]);
        }

        return back()->with('status', 'Supplier disimpan.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->vendorServices()->exists()) {
            return back()->with('error', "{$supplier->name} masih punya jasa vendor. Hapus jasanya di menu Jasa Vendor dulu, atau nonaktifkan supplier ini.");
        }

        if ($supplier->deposits()->exists()) {
            return back()->with('error', "{$supplier->name} punya catatan uang muka. Nonaktifkan saja bila tidak dipakai lagi.");
        }

        if ($supplier->purchases()->exists()) {
            return back()->with('error', "{$supplier->name} sudah punya pembelian. Nonaktifkan saja bila tidak dipakai lagi.");
        }

        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier dihapus.');
    }

    /** Bayar beberapa tagihan sekaligus (dari dompet dan/atau saldo uang muka). */
    public function payMany(Request $request, Supplier $supplier, PurchaseService $service): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date'],
            'purchases' => ['required', 'array', 'min:1'],
            'purchases.*' => ['integer'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'wallet_id' => ['nullable', 'exists:wallets,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['purchases.required' => 'Centang tagihan yang mau dibayar.']);

        try {
            $payments = $service->payMany($supplier, $data['purchases'], (float) ($data['amount'] ?? 0), isset($data['wallet_id']) ? (int) $data['wallet_id'] : null,
                $request->boolean('use_deposit'), $data['paid_at'], $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', rupiah($payments->sum('amount')).' dibayarkan ke '.$payments->pluck('material_purchase_id')->unique()->count().' tagihan.');
    }

    public function storeDeposit(Request $request, Supplier $supplier, PurchaseService $service): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'wallet_id' => ['required', 'exists:wallets,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $service->giveDeposit($supplier, (float) $data['amount'], (int) $data['wallet_id'], $data['paid_at'], $data['notes'] ?? null);

        return back()->with('status', 'Uang muka dicatat. Pakai saat membayar tagihan '.$supplier->name.'.');
    }

    public function destroyDeposit(SupplierDeposit $deposit, PurchaseService $service): RedirectResponse
    {
        try {
            $service->deleteDeposit($deposit);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Uang muka dihapus; saldo dompet dikembalikan.');
    }

    /** @return array{name: string, phone: ?string, address: ?string, payment_term_days: ?int, notes: ?string} */
    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplier)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string'],
        ], ['name.unique' => 'Supplier dengan nama ini sudah ada.']);
    }
}
