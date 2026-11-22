<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Production;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** Input produksi: knalpot jadi bertambah, bahan berkurang sesuai resep. */
class ProductionController extends Controller
{
    public function index(Request $request): View
    {
        $productions = Production::query()->with(['item', 'formula'])
            ->whereNotNull('item_id')
            ->when($request->input('month'), fn ($query, $month) => $query->where('produced_at', 'like', "{$month}%"))
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->whereHas('item', fn ($query) => $query->where('name', 'like', "%{$search}%")))
            ->latest('produced_at')->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.productions.index', ['productions' => $productions]);
    }

    public function show(Production $production): View
    {
        return view('admin.productions.show', ['production' => $production->load('item', 'formula', 'materials.material')]);
    }

    public function create(): View
    {
        return view('admin.productions.create', ['items' => Item::where('is_active', true)->with('formula')->orderBy('sku')->get()]);
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'produced_at' => ['required', 'date'],
            'item_id' => ['required', 'exists:items,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'worker' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $production = $inventory->recordProduction(Item::findOrFail($data['item_id']), (float) $data['qty'], $data['produced_at'], $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['item_id' => $e->getMessage()]);
        }

        return redirect()->route('admin.productions.show', $production)->with('status', 'Produksi dicatat. Stok knalpot bertambah dan bahan berkurang.');
    }

    public function destroy(Production $production, InventoryService $inventory): RedirectResponse
    {
        $inventory->deleteProduction($production);

        return redirect()->route('admin.productions.index')->with('status', 'Produksi dihapus dan stok dikembalikan.');
    }
}
