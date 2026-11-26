<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\MaterialMovement;
use App\Models\MaterialStock;
use App\Models\PipePiece;
use App\Models\Production;
use App\Models\Warehouse;
use Illuminate\View\View;

/** Empat gudang: bahan baku, setengah jadi, stok sisa produksi, finish good. */
class WarehouseController extends Controller
{
    public function index(): View
    {
        $cards = collect(array_keys(Warehouse::TYPES))->map(function (string $type) {
            $warehouse = Warehouse::forType($type);

            if ($type === Warehouse::VENDOR) {
                $jobs = Production::query()->posted()->where('mode', Production::VENDOR)->get();

                return ['warehouse' => $warehouse, 'value' => (float) $jobs->sum(fn (Production $job) => $job->outstandingValue()), 'count' => $jobs->filter(fn (Production $job) => $job->outstandingQty() > 0)->count(), 'unit' => 'pekerjaan', 'qty' => (float) $jobs->sum(fn (Production $job) => $job->outstandingQty())];
            }

            if ($type === Warehouse::FINISHED) {
                $items = Item::query()->where('stock', '>', 0)->get(['stock', 'cost_price']);

                return ['warehouse' => $warehouse, 'value' => (float) $items->sum(fn (Item $item) => $item->stockValue()), 'count' => $items->count(), 'unit' => 'produk', 'qty' => (float) $items->sum('stock')];
            }

            $stocks = MaterialStock::query()->where('warehouse_id', $warehouse->id)->where('qty', '>', 0)->get();

            return [
                'warehouse' => $warehouse,
                'value' => (float) $stocks->sum('value'),
                'count' => $stocks->count(),
                'unit' => 'bahan',
                'qty' => $type === Warehouse::OFFCUT ? PipePiece::where('warehouse_id', $warehouse->id)->where('status', PipePiece::AVAILABLE)->count() : null,
            ];
        });

        return view('admin.warehouses.index', [
            'cards' => $cards,
            'movements' => MaterialMovement::query()->with('material', 'warehouse')->latest('moved_at')->latest('id')->limit(15)->get(),
        ]);
    }

    public function show(string $type): View
    {
        $warehouse = Warehouse::forType($type);

        if ($warehouse->type === Warehouse::FINISHED) {
            return view('admin.warehouses.finished', [
                'warehouse' => $warehouse,
                'items' => Item::query()->with('formula')->where('is_active', true)->orderByDesc('stock')->orderBy('name')->get(),
            ]);
        }

        if ($warehouse->type === Warehouse::VENDOR) {
            $jobs = Production::query()->with('formula.outputMaterial')->posted()->where('mode', Production::VENDOR)
                ->latest('produced_at')->latest('id')->get();

            return view('admin.warehouses.vendor', [
                'warehouse' => $warehouse,
                'open' => $jobs->filter(fn (Production $job) => $job->outstandingQty() > 0)->values(),
                'done' => $jobs->filter(fn (Production $job) => $job->outstandingQty() <= 0)->take(20)->values(),
            ]);
        }

        if ($warehouse->type === Warehouse::OFFCUT) {
            return view('admin.warehouses.offcut', [
                'warehouse' => $warehouse,
                'pieces' => PipePiece::query()->with('material')->where('warehouse_id', $warehouse->id)->where('status', PipePiece::AVAILABLE)
                    ->orderBy('material_id')->orderByDesc('length_cm')->get()->groupBy('material_id'),
                'scrap' => PipePiece::query()->with('material')->where('status', PipePiece::SCRAP)->latest('updated_at')->limit(30)->get(),
            ]);
        }

        return view('admin.warehouses.show', [
            'warehouse' => $warehouse,
            'stocks' => MaterialStock::query()->with('material.category')->where('warehouse_id', $warehouse->id)
                ->get()->sortBy(fn (MaterialStock $stock) => $stock->material->name)->values(),
            'pieceCounts' => PipePiece::query()->where('warehouse_id', $warehouse->id)->where('status', PipePiece::AVAILABLE)
                ->selectRaw('material_id, COUNT(*) AS total')->groupBy('material_id')->pluck('total', 'material_id'),
            'movements' => MaterialMovement::query()->with('material')->where('warehouse_id', $warehouse->id)->latest('moved_at')->latest('id')->paginate(20),
        ]);
    }
}
