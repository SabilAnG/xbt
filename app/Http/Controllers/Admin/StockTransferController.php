<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\PipePiece;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

/** Pindah Barang antar gudang, seperti di EMS. */
class StockTransferController extends Controller
{
    public function index(Request $request): View
    {
        $transfers = StockTransfer::query()->with('fromWarehouse', 'toWarehouse', 'items.material', 'items.piece')
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('transfer_number', 'like', "%{$search}%")->orWhere('moved_by', 'like', "%{$search}%")
                    ->orWhereHas('items.material', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ))
            ->when($request->filled('gudang'), fn ($query) => $query->where(
                fn ($query) => $query->where('from_warehouse_id', $request->integer('gudang'))->orWhere('to_warehouse_id', $request->integer('gudang'))
            ))
            ->latest('transferred_at')->latest('id');

        return view('admin.transfers.index', [
            'transfers' => $transfers->paginate(20)->withQueryString(),
            'warehouses' => $this->warehouses(),
        ]);
    }

    public function create(Request $request): View
    {
        $warehouses = $this->warehouses();

        return view('admin.transfers.create', [
            'warehouses' => $warehouses,
            'from' => (int) old('from_warehouse_id', $request->integer('dari') ?: $warehouses->keys()->first()),
            'materials' => Material::query()->active()->where('measure', Material::UNIT)->with('stocks')->orderBy('name')->get()
                ->filter(fn (Material $material) => (float) $material->stock > 0)->values(),
            'pieces' => PipePiece::query()->with('material', 'warehouse')->where('status', PipePiece::AVAILABLE)
                ->orderBy('material_id')->orderByDesc('length_cm')->get(),
            'lines' => array_values(old('items', [['material_id' => '', 'qty' => '']])),
        ]);
    }

    public function store(Request $request, StockTransferService $service): RedirectResponse
    {
        $data = $request->validate([
            'transferred_at' => ['required', 'date'],
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'moved_by' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.material_id' => ['nullable', 'exists:materials,id'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'pieces' => ['nullable', 'array'],
            'pieces.*' => ['integer', 'exists:pipe_pieces,id'],
        ], ['to_warehouse_id.different' => 'Gudang tujuan harus berbeda dari gudang asal.']);

        try {
            $transfer = $service->create($data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.transfers.show', $transfer)->with('status', 'Barang dipindahkan.');
    }

    public function show(StockTransfer $transfer): View
    {
        return view('admin.transfers.show', [
            'transfer' => $transfer->load('fromWarehouse', 'toWarehouse', 'items.material', 'items.piece'),
        ]);
    }

    public function destroy(StockTransfer $transfer, StockTransferService $service): RedirectResponse
    {
        try {
            $service->delete($transfer);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.transfers.index')->with('status', 'Pindah barang dibatalkan; barang kembali ke gudang asal.');
    }

    /** @return Collection<int, string> */
    private function warehouses(): Collection
    {
        return collect(StockTransferService::warehouseTypes())->mapWithKeys(fn (string $type) => [Warehouse::forType($type)->id => Warehouse::forType($type)->name]);
    }
}
