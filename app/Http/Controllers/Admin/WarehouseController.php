<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\ComponentMovement;
use App\Models\Item;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Warehouse;
use App\Services\ComponentStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Tiga gudang: bahan baku (stok bahan), setengah jadi (stok komponen), dan
 * finish good (stok knalpot jadi).
 */
class WarehouseController extends Controller
{
    public function index(): View
    {
        $summaries = collect(Warehouse::TYPES)->keys()
            ->map(fn (string $type) => ['warehouse' => Warehouse::forType($type)] + $this->summary($type));

        return view('admin.warehouses.index', ['summaries' => $summaries]);
    }

    public function show(Request $request, string $type): View
    {
        $warehouse = Warehouse::forType($type);
        $search = $request->string('q')->trim()->value();
        $status = $request->input('status');

        $rows = match ($warehouse->type) {
            'bahan_baku' => Material::query()->with('category')
                ->when($search, fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
                ->when($request->input('category'), fn ($query, $category) => $query->where('material_category_id', $category))
                ->where('is_active', true)->orderBy('sku')->get(),
            'setengah_jadi' => Component::query()->with('materials.material')
                ->when($search, fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
                ->when($request->input('group'), fn ($query, $group) => $query->where('group', $group))
                ->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(),
            default => Item::query()
                ->when($search, fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
                ->where('is_active', true)->orderBy('sku')->get(),
        };

        if ($status) {
            $rows = $rows->filter(fn ($row) => $row->stockStatus() === $status)->values();
        }

        return view('admin.warehouses.show', [
            'warehouse' => $warehouse,
            'rows' => $rows,
            'summary' => $this->summary($warehouse->type),
            'categories' => $warehouse->type === 'bahan_baku' ? MaterialCategory::orderBy('sort_order')->pluck('name', 'id') : collect(),
            'recent' => $warehouse->type === 'setengah_jadi'
                ? ComponentMovement::with('component')->latest('moved_at')->latest('id')->limit(15)->get()
                : collect(),
        ]);
    }

    public function edit(string $type): View
    {
        return view('admin.warehouses.edit', ['warehouse' => Warehouse::forType($type)]);
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        $warehouse = Warehouse::forType($type);
        $warehouse->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ]));

        return redirect()->route('admin.warehouses.show', $warehouse->slug())->with('status', 'Info gudang disimpan.');
    }

    public function createMovement(Request $request): View
    {
        return view('admin.warehouses.movement', [
            'components' => Component::query()->with('materials.material')->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(),
            'selected' => $request->integer('component') ?: null,
            'type' => $request->input('type', 'masuk'),
        ]);
    }

    public function storeMovement(Request $request, ComponentStockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'component_id' => ['required', 'exists:components,id'],
            'type' => ['required', 'in:'.implode(',', array_keys(ComponentMovement::TYPES))],
            'qty' => ['required', 'numeric', 'not_in:0'],
            'moved_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $stock->record(
                Component::findOrFail($data['component_id']),
                $data['type'],
                (float) $data['qty'],
                $data['moved_at'],
                $data['notes'] ?? null,
                $request->boolean('consume_materials'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('admin.warehouses.show', 'setengah-jadi')->with('status', 'Mutasi komponen dicatat.');
    }

    public function destroyMovement(ComponentMovement $movement, ComponentStockService $stock): RedirectResponse
    {
        $stock->delete($movement);

        return back()->with('status', 'Mutasi dihapus. Stok komponen dan bahan baku dihitung ulang.');
    }

    /**
     * @return array{count: int, qty: float, value: float, low: int}
     */
    private function summary(string $type): array
    {
        $rows = match ($type) {
            'bahan_baku' => Material::where('is_active', true)->get(),
            'setengah_jadi' => Component::with('materials.material')->where('is_active', true)->get(),
            default => Item::where('is_active', true)->get(),
        };

        return [
            'count' => $rows->count(),
            'qty' => (float) $rows->sum(fn ($row) => max((float) $row->stock, 0)),
            'value' => (float) $rows->sum(fn ($row) => match ($type) {
                'setengah_jadi' => max($row->stockValue(), 0),
                default => max((float) $row->stock, 0) * (float) $row->cost_price,
            }),
            'low' => $rows->filter(fn ($row) => $row->stockStatus() !== 'AMAN')->count(),
        ];
    }
}
