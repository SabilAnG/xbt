<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** Pembelian bahan baku: stok bahan bertambah. */
class MaterialPurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $purchases = MaterialPurchase::query()->with(['items.material', 'vendor'])
            ->when($request->input('month'), fn ($query, $month) => $query->where('purchased_at', 'like', "{$month}%"))
            ->when($request->input('material'), fn ($query, $material) => $query->whereHas('items', fn ($query) => $query->where('material_id', $material)))
            ->latest('purchased_at')->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.purchases.index', [
            'purchases' => $purchases,
            'materials' => Material::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.purchases.create', ['materials' => Material::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'purchased_at' => ['required', 'date'],
            'material_id' => ['required', 'exists:materials,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'paid_from' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['recorded_in_expenses'] = $request->boolean('recorded_in_expenses');

        try {
            $inventory->recordMaterialPurchase(Material::findOrFail($data['material_id']), (float) $data['qty'], (float) $data['unit_price'], $data['purchased_at'], $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('admin.purchases.index')->with('status', 'Pembelian dicatat. Stok bahan bertambah.');
    }

    public function destroy(MaterialPurchase $purchase, InventoryService $inventory): RedirectResponse
    {
        $inventory->deleteMaterialPurchase($purchase);

        return redirect()->route('admin.purchases.index')->with('status', 'Pembelian dihapus dan stok bahan dikurangi kembali.');
    }
}
