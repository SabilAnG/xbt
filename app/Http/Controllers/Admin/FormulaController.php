<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Formula;
use App\Models\Item;
use App\Models\Material;
use App\Models\Production;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Resep BOM — bahan per satu knalpot. Baris bermerek (FD/XBT) hanya dipakai
 * untuk produk merek tersebut; baris tanpa merek berlaku untuk semua.
 */
class FormulaController extends Controller
{
    public function index(): View
    {
        return view('admin.formulas.index', [
            'formulas' => Formula::query()->withCount(['materials'])
                ->addSelect(['items_count' => Item::selectRaw('COUNT(*)')->whereColumn('items.formula_id', 'formulas.id')])
                ->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Formula);
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $formula = DB::transaction(function () use ($request) {
            $data = $this->validated($request);
            $formula = Formula::create([
                'code' => Str::upper(Str::slug($data['name'])),
                'name' => $data['name'],
                'notes' => $data['notes'] ?? null,
                'output_qty' => 1,
                'output_unit' => 'pcs',
            ]);
            $this->syncLines($formula, $data['lines'] ?? []);

            return $formula;
        });

        $inventory->syncFormula($formula);

        return redirect()->route('admin.formulas.edit', $formula)->with('status', 'Resep dibuat.');
    }

    public function edit(Formula $formula): View
    {
        return $this->form($formula->load('materials.material'));
    }

    /** Resep diubah: pemakaian bahan produksi lama ikut dihitung ulang, seperti Excel. */
    public function update(Request $request, Formula $formula, InventoryService $inventory): RedirectResponse
    {
        DB::transaction(function () use ($request, $formula) {
            $data = $this->validated($request, $formula);
            $formula->update(['name' => $data['name'], 'notes' => $data['notes'] ?? null]);
            $this->syncLines($formula, $data['lines'] ?? []);
        });

        $inventory->syncFormula($formula);

        return redirect()->route('admin.formulas.edit', $formula)->with('status', 'Resep disimpan. Stok bahan dihitung ulang.');
    }

    public function destroy(Formula $formula): RedirectResponse
    {
        if ($formula->productions()->exists()) {
            return back()->with('error', 'Resep sudah dipakai produksi, jadi tidak bisa dihapus.');
        }

        DB::transaction(function () use ($formula) {
            Item::where('formula_id', $formula->id)->update(['formula_id' => null]);
            $formula->materials()->delete();
            $formula->delete();
        });

        return redirect()->route('admin.formulas.index')->with('status', 'Resep dihapus.');
    }

    private function form(Formula $formula): View
    {
        // Kolom "Knalpot Diproduksi": qty produksi produk beresep ini dengan
        // merek yang cocok (baris "Semua" = semua produk yang punya merek).
        $producedByBrand = $formula->exists
            ? Production::query()->where('productions.formula_id', $formula->id)
                ->join('items', 'items.id', '=', 'productions.item_id')
                ->selectRaw('items.brand AS brand, SUM(productions.output_qty) AS qty')
                ->groupBy('items.brand')
                ->pluck('qty', 'brand')
            : collect();

        return view('admin.formulas.form', [
            'formula' => $formula,
            'producedByBrand' => $producedByBrand,
            'materials' => Material::orderBy('name')->get(),
            'brands' => Item::query()->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand'),
        ]);
    }

    /**
     * @return array{name: string, notes?: ?string, lines?: array<int, array{material_id: ?int, brand: ?string, qty: ?float}>}
     */
    private function validated(Request $request, ?Formula $formula = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('formulas', 'name')->ignore($formula)],
            'notes' => ['nullable', 'string'],
            'lines' => ['array'],
            'lines.*.material_id' => ['nullable', 'exists:materials,id'],
            'lines.*.brand' => ['nullable', 'string', 'max:50'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['lines'] = collect($data['lines'] ?? [])
            ->filter(fn (array $line) => ! empty($line['material_id']) && (float) ($line['qty'] ?? 0) > 0)
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  array<int, array{material_id: ?int, brand: ?string, qty: ?float}>  $lines
     */
    private function syncLines(Formula $formula, array $lines): void
    {
        $formula->materials()->delete();

        foreach ($lines as $order => $line) {
            $formula->materials()->updateOrCreate(
                ['material_id' => $line['material_id']],
                [
                    'brand' => $line['brand'] ?: null,
                    'input_mode' => 'direct',
                    'qty' => $line['qty'],
                    'sort_order' => $order,
                ],
            );
        }
    }
}
