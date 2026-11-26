<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialPurchase;
use App\Models\Production;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
        }

        return back()->with('status', 'Supplier disimpan.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchases()->exists()) {
            return back()->with('error', "{$supplier->name} sudah punya pembelian. Nonaktifkan saja bila tidak dipakai lagi.");
        }

        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier dihapus.');
    }

    /** @return array{name: string, phone: ?string, address: ?string, notes: ?string} */
    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplier)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ], ['name.unique' => 'Supplier dengan nama ini sudah ada.']);
    }
}
