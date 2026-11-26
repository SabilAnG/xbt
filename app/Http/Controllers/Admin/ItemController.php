<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Item;
use App\Models\ItemAdjustment;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

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
            'item' => $item->load('formula'),
            'movements' => $item->movements()->paginate(20),
            'adjustments' => ItemAdjustment::query()->where('item_id', $item->id)->latest('adjusted_at')->latest('id')->limit(10)->get(),
            'reasons' => ItemAdjustment::REASONS,
        ]);
    }

    /** Stok opname produk jadi: isi stok hasil hitung fisik. */
    public function adjust(Request $request, Item $item, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'adjusted_at' => ['required', 'date'],
            'actual_qty' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', Rule::in(array_keys(ItemAdjustment::REASONS))],
            'counted_by' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $adjustment = $inventory->adjustItem($item, (float) $data['actual_qty'], $data['reason'], $data['adjusted_at'], $data['notes'] ?? null, $data['counted_by'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Stok disesuaikan ('.((float) $adjustment->difference > 0 ? '+' : '').qty((float) $adjustment->difference).').');
    }

    public function destroyAdjustment(ItemAdjustment $adjustment, InventoryService $inventory): RedirectResponse
    {
        try {
            $inventory->deleteItemAdjustment($adjustment);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Penyesuaian dihapus; stok dikembalikan.');
    }

    public function create(): View
    {
        return view('admin.items.form', [
            'item' => new Item(['min_stock' => 2, 'unit' => 'pcs', 'is_active' => true]),
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
        return view('admin.items.form', ['item' => $item, 'options' => $this->options()]);
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
        return collect(['model_motor' => 'model_motor', 'finishing' => 'finishing', 'tipe' => 'variant', 'ukuran' => 'size'])
            ->map(fn (string $column) => Item::query()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column))
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
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'sell_price_online' => ['required', 'numeric', 'min:0'],
            'opening_stock' => ['nullable', 'numeric'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['opening_stock'] = $data['opening_stock'] ?? 0;

        // Nama produk otomatis seperti di buku kerja bila dikosongkan.
        $data['name'] = ($data['name'] ?? null) ?: collect([$data['model_motor'], $data['finishing'] ?? null, $data['variant'] ?? null, $data['size'] ?? null, $data['brand'] ?? null])
            ->filter()->implode(' ');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
