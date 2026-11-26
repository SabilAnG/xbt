<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemAdjustment;
use App\Models\Material;
use App\Models\MaterialAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Services\AdjustmentService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * Stok opname per gudang: hitung fisik banyak barang sekaligus. Hanya baris
 * yang stok fisiknya diisi dan berbeda dari sistem yang dibuatkan
 * penyesuaian. Pipa dihitung per batang lewat Penyesuaian Stok.
 */
class StockOpnameController extends Controller
{
    /** Gudang yang bisa diopname di halaman ini, urut tampil. */
    private const WAREHOUSES = [Warehouse::FINISHED, Warehouse::RAW, Warehouse::SEMI, Warehouse::ACCESSORY];

    public function index(Request $request): View
    {
        $warehouses = collect(self::WAREHOUSES)->map(fn (string $type) => Warehouse::forType($type));
        $current = $warehouses->first(fn (Warehouse $warehouse) => in_array($request->input('gudang'), [$warehouse->slug(), $warehouse->type], true)) ?? $warehouses->first();

        return view('admin.stock-opname.index', [
            'warehouse' => $current,
            'tiles' => $warehouses->map(function (Warehouse $warehouse) {
                $rows = $this->rows($warehouse);

                return ['warehouse' => $warehouse, 'count' => $rows->count(), 'value' => (float) $rows->sum(fn (array $row) => $row['system'] * $row['cost'])];
            }),
            'rows' => $this->rows($current),
            'reasons' => ItemAdjustment::REASONS,
            'lastCount' => $this->lastCount($current),
            // Saran nama pelaksana: pekerja aktif & pengguna panel.
            'counters' => Worker::query()->where('is_active', true)->orderBy('name')->pluck('name')
                ->merge(User::query()->where('is_active', true)->orderBy('name')->pluck('name'))->unique()->values(),
        ]);
    }

    public function storeItems(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'adjusted_at' => ['required', 'date'],
            'counted_by' => ['required', 'string', 'max:100'],
            'reason' => ['required', Rule::in(array_keys(ItemAdjustment::REASONS))],
            'notes' => ['nullable', 'string', 'max:255'],
            'actual' => ['array'],
            'actual.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = Item::query()->whereIn('id', array_keys(array_filter($data['actual'] ?? [], 'filled')))->get();
        $changed = $items->filter(fn (Item $item) => abs((float) $data['actual'][$item->id] - (float) $item->stock) > 0.0001);

        if ($changed->isEmpty()) {
            return back()->withInput()->with('error', 'Tidak ada stok fisik yang berbeda dari stok sistem.');
        }

        try {
            DB::transaction(fn () => $changed->each(fn (Item $item) => $inventory->adjustItem($item, (float) $data['actual'][$item->id], $data['reason'], $data['adjusted_at'], ($data['notes'] ?? null) ?: 'Stok opname', $data['counted_by'])));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-opname.index')->with('status', 'Stok opname Finish Good disimpan: '.$changed->count().' produk disesuaikan.');
    }

    public function storeMaterials(Request $request, AdjustmentService $adjustments): RedirectResponse
    {
        $data = $request->validate([
            'warehouse' => ['required', Rule::in([Warehouse::RAW, Warehouse::SEMI, Warehouse::ACCESSORY])],
            'adjusted_at' => ['required', 'date'],
            'counted_by' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'actual' => ['array'],
            'actual.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $warehouse = Warehouse::forType($data['warehouse']);
        $materials = $this->materialsIn($warehouse)->whereIn('id', array_keys(array_filter($data['actual'] ?? [], 'filled')));
        $changed = $materials->filter(fn (Material $material) => abs((float) $data['actual'][$material->id] - $material->stockIn($warehouse)) > 0.0001);

        if ($changed->isEmpty()) {
            return back()->withInput()->with('error', 'Tidak ada stok fisik yang berbeda dari stok sistem.');
        }

        try {
            DB::transaction(fn () => $changed->each(function (Material $material) use ($data, $warehouse, $adjustments) {
                $difference = (float) $data['actual'][$material->id] - $material->stockIn($warehouse);
                $adjustments->create([
                    'adjusted_at' => $data['adjusted_at'], 'material_id' => $material->id, 'warehouse_id' => $warehouse->id,
                    'direction' => $difference > 0 ? 'in' : 'out', 'qty' => abs($difference), 'reason' => 'koreksi', 'counted_by' => $data['counted_by'],
                    'notes' => ($data['notes'] ?? null) ?: 'Stok opname',
                ]);
            }));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.stock-opname.index', ['gudang' => $warehouse->slug()])
            ->with('status', 'Stok opname '.$warehouse->name.' disimpan: '.$changed->count().' bahan disesuaikan.');
    }

    /**
     * Baris opname untuk satu gudang (produk jadi atau bahan per satuan).
     *
     * @return Collection<int, array{id: int, name: string, meta: string, unit: string, system: float, cost: float, href: ?string}>
     */
    private function rows(Warehouse $warehouse): Collection
    {
        if ($warehouse->type === Warehouse::FINISHED) {
            return Item::query()->where('is_active', true)->orderBy('name')->get()->map(fn (Item $item) => [
                'id' => $item->id, 'name' => $item->name, 'meta' => $item->sku, 'unit' => $item->unit ?: 'pcs',
                'system' => (float) $item->stock, 'cost' => (float) $item->cost_price, 'href' => route('admin.items.show', $item),
            ]);
        }

        return $this->materialsIn($warehouse)->map(fn (Material $material) => [
            'id' => $material->id, 'name' => $material->name, 'meta' => $material->category?->name ?? '', 'unit' => $material->stockUnit(),
            'system' => $material->stockIn($warehouse), 'cost' => $material->unitCost(), 'href' => null,
        ]);
    }

    /** Opname terakhir di gudang ini: tanggal & pelaksana. */
    private function lastCount(Warehouse $warehouse): ?object
    {
        $query = $warehouse->type === Warehouse::FINISHED
            ? ItemAdjustment::query()
            : MaterialAdjustment::query()->where('warehouse_id', $warehouse->id);

        return $query->whereNotNull('counted_by')->latest('adjusted_at')->latest('id')->first(['adjusted_at', 'counted_by']);
    }

    /** @return Collection<int, Material> */
    private function materialsIn(Warehouse $warehouse): Collection
    {
        return Material::query()->active()->with('stocks', 'category')->orderBy('name')->get()
            ->reject(fn (Material $material) => $material->isPipe())
            ->filter(fn (Material $material) => $material->warehouse_id === $warehouse->id || $material->stockIn($warehouse) > 0)
            ->values();
    }
}
