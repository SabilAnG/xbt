<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialAdjustment;
use App\Models\PipePiece;
use App\Models\Warehouse;
use App\Services\AdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Penyesuaian stok bahan: stok awal, koreksi hitung fisik, barang rusak. */
class AdjustmentController extends Controller
{
    public function index(): View
    {
        return view('admin.adjustments.index', [
            'adjustments' => MaterialAdjustment::query()->with('material', 'warehouse', 'piece')->latest('adjusted_at')->latest('id')->paginate(25),
        ]);
    }

    public function create(Request $request): View
    {
        $materials = Material::query()->active()->with('warehouse')->orderBy('name')->get();

        return view('admin.adjustments.create', [
            'materials' => $materials,
            'selected' => $request->integer('material') ?: null,
            'warehouses' => collect([Warehouse::RAW, Warehouse::SEMI, Warehouse::ACCESSORY, Warehouse::OFFCUT])->mapWithKeys(fn (string $type) => [Warehouse::forType($type)->id => Warehouse::forType($type)->name]),
            'pieces' => PipePiece::query()->with('warehouse')->where('status', PipePiece::AVAILABLE)->orderBy('length_cm')->get()
                ->groupBy('material_id')->map(fn ($group) => $group->map(fn (PipePiece $piece) => [
                    'id' => $piece->id, 'label' => $piece->code.' · '.qty($piece->length_cm, 2).' cm · '.$piece->warehouse->typeLabel(),
                ])->values()),
        ]);
    }

    public function store(Request $request, AdjustmentService $service): RedirectResponse
    {
        $material = Material::find($request->input('material_id'));
        $pipe = $material?->isPipe();
        $pipeOut = $pipe && $request->input('direction') === 'out';

        $data = $request->validate([
            'adjusted_at' => ['required', 'date'],
            'material_id' => ['required', 'exists:materials,id'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'reason' => ['required', Rule::in(array_keys(MaterialAdjustment::REASONS))],
            'warehouse_id' => [$pipeOut ? 'nullable' : 'required', 'exists:warehouses,id'],
            'qty' => [$pipeOut ? 'nullable' : 'required', 'numeric', 'gt:0', ...($pipe ? ['integer'] : [])],
            'length_cm' => [$pipe && ! $pipeOut ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'pipe_piece_id' => [$pipeOut ? 'required' : 'nullable', 'exists:pipe_pieces,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'qty.integer' => 'Untuk pipa, isi jumlah batang/potongan (bilangan bulat).',
            'length_cm.required' => 'Isi panjang tiap batang/potongan pipa.',
            'pipe_piece_id.required' => 'Pilih potongan pipa yang diafkir.',
        ]);

        // Bahan per satuan selalu di gudang asalnya; pipa boleh ke Bahan Baku (batang utuh) atau Stok Sisa (potongan).
        $allowed = $pipe ? [$material->warehouse_id, Warehouse::forType(Warehouse::OFFCUT)->id] : [$material->warehouse_id];
        $data['warehouse_id'] = in_array((int) ($data['warehouse_id'] ?? 0), $allowed, true) ? (int) $data['warehouse_id'] : $material->warehouse_id;

        try {
            $service->create($data);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.adjustments.index')->with('status', 'Penyesuaian stok dicatat.');
    }

    public function destroy(MaterialAdjustment $adjustment, AdjustmentService $service): RedirectResponse
    {
        try {
            $service->delete($adjustment);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Penyesuaian dihapus.');
    }
}
