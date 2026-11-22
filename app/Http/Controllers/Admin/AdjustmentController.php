<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** Penyesuaian stok bahan — bahan rusak/hilang atau koreksi hitung fisik. */
class AdjustmentController extends Controller
{
    public function index(): View
    {
        return view('admin.adjustments.index', [
            'adjustments' => MaterialMovement::query()->with('material')->where('type', 'adjustment')
                ->latest('moved_at')->latest('id')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.adjustments.create', ['materials' => Material::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'moved_at' => ['required', 'date'],
            'material_id' => ['required', 'exists:materials,id'],
            'qty' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $inventory->adjustMaterial(Material::findOrFail($data['material_id']), (float) $data['qty'], $data['moved_at'], $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['qty' => $e->getMessage()]);
        }

        return redirect()->route('admin.adjustments.index')->with('status', 'Penyesuaian dicatat.');
    }

    public function destroy(MaterialMovement $adjustment, InventoryService $inventory): RedirectResponse
    {
        try {
            $inventory->deleteAdjustment($adjustment);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.adjustments.index')->with('status', 'Penyesuaian dihapus.');
    }
}
