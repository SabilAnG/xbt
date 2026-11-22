<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Formula;
use App\Models\Item;
use App\Models\ListOption;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Stok Produk — knalpot jadi. */
class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $items = Item::query()->with('formula')
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")
            ))
            ->when($request->input('brand'), fn ($query, $brand) => $query->where('brand', $brand))
            ->when($request->input('status'), function ($query, $status) {
                match ($status) {
                    'HABIS' => $query->where('stock', '<=', 0),
                    'MENIPIS' => $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'min_stock'),
                    'AMAN' => $query->whereColumn('stock', '>', 'min_stock')->where('stock', '>', 0),
                    default => null,
                };
            })
            ->orderBy('sku')
            ->paginate(25)
            ->withQueryString();

        $flow = DB::table('stock_movements')
            ->whereIn('item_id', $items->pluck('id'))
            ->selectRaw("item_id,
                SUM(CASE WHEN type = 'production' THEN qty_in ELSE 0 END) AS produced,
                SUM(CASE WHEN type = 'sale' THEN qty_out - qty_in ELSE 0 END) AS sold")
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        return view('admin.items.index', [
            'items' => $items,
            'flow' => $flow,
            'brands' => Brand::names(),
        ]);
    }

    public function show(Item $item): View
    {
        return view('admin.items.show', [
            'item' => $item->load('formula.materials.material'),
            'movements' => $item->movements()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.items.form', [
            'item' => new Item(['min_stock' => 2, 'unit' => 'pcs', 'is_active' => true]),
            'formulas' => Formula::orderBy('name')->get(),
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $item = Item::create($this->validated($request));
        $item->recalculateStock();

        return redirect()->route('admin.items.show', $item)->with('status', 'Produk ditambahkan.');
    }

    public function edit(Item $item): View
    {
        return view('admin.items.form', ['item' => $item, 'formulas' => Formula::orderBy('name')->get(), 'options' => $this->options()]);
    }

    /**
     * Resep, merek, HPP, atau stok awal yang berubah ikut mengubah produksi &
     * penjualan lama — Excel menghitung semuanya live.
     */
    public function update(Request $request, Item $item, InventoryService $inventory): RedirectResponse
    {
        $item->update($this->validated($request, $item));
        $inventory->syncItem($item);

        return redirect()->route('admin.items.show', $item)->with('status', 'Produk diperbarui.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        if ($item->movements()->exists()) {
            return back()->with('error', 'Produk sudah punya riwayat stok, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }

        $item->delete();

        return redirect()->route('admin.items.index')->with('status', 'Produk dihapus.');
    }

    /**
     * @return array<string, Collection<int, string>>
     */
    private function options(): array
    {
        return collect(['model_motor', 'finishing', 'tipe', 'ukuran'])
            ->mapWithKeys(fn (string $list) => [$list => ListOption::valuesFor($list)])
            ->put('merek', Brand::query()->ordered()->where('is_active', true)->pluck('name'))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Item $item = null): array
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50', Rule::unique('items', 'sku')->ignore($item)],
            'model_motor' => ['required', 'string', 'max:100'],
            'finishing' => ['nullable', 'string', 'max:100'],
            'variant' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:50'],
            'brand' => ['nullable', 'string', Rule::exists('brands', 'name')],
            'name' => ['nullable', 'string', 'max:255'],
            'formula_id' => ['nullable', 'exists:formulas,id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'sell_price_online' => ['required', 'numeric', 'min:0'],
            'opening_stock' => ['nullable', 'numeric'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['opening_stock'] = $data['opening_stock'] ?? 0;

        // Nama produk otomatis seperti di buku kerja bila dikosongkan.
        $data['name'] = ($data['name'] ?? null) ?: collect([$data['model_motor'], $data['finishing'], $data['variant'], $data['size'], $data['brand']])
            ->filter()->implode(' ');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
