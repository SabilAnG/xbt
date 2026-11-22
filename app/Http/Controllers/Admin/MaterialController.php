<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Stok Bahan — bahan baku. */
class MaterialController extends Controller
{
    public function index(Request $request): View
    {
        $materials = Material::query()->with('category')
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($request->input('category'), fn ($query, $category) => $query->where('material_category_id', $category))
            ->orderBy('sku')
            ->get();

        if ($status = $request->input('status')) {
            $materials = $materials->filter(fn (Material $material) => $material->stockStatus() === $status)->values();
        }

        $flow = DB::table('material_movements')
            ->selectRaw("material_id,
                SUM(CASE WHEN type = 'purchase' THEN qty_in ELSE 0 END) AS masuk,
                SUM(CASE WHEN type = 'production' THEN qty_out ELSE 0 END) AS terpakai,
                SUM(CASE WHEN type IN ('adjustment', 'opname') THEN qty_in - qty_out ELSE 0 END) AS penyesuaian")
            ->groupBy('material_id')
            ->get()
            ->keyBy('material_id');

        return view('admin.materials.index', [
            'materials' => $materials,
            'flow' => $flow,
            'categories' => MaterialCategory::orderBy('sort_order')->get(),
        ]);
    }

    public function show(Material $material): View
    {
        return view('admin.materials.show', [
            'material' => $material->load('category', 'formulaLines.formula'),
            'movements' => $material->movements()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.materials.form', [
            'material' => new Material(['unit' => 'Pcs', 'is_active' => true]),
            'categories' => MaterialCategory::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $material = Material::create($this->validated($request));
        $material->recalculateStock();

        return redirect()->route('admin.materials.show', $material)->with('status', 'Bahan ditambahkan.');
    }

    public function edit(Material $material): View
    {
        return view('admin.materials.form', ['material' => $material, 'categories' => MaterialCategory::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Material $material): RedirectResponse
    {
        $material->update($this->validated($request, $material));
        $material->recalculateStock();

        return redirect()->route('admin.materials.show', $material)->with('status', 'Bahan diperbarui.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        if ($material->movements()->exists() || $material->formulaLines()->exists()) {
            return back()->with('error', 'Bahan sudah dipakai di resep atau punya riwayat stok, jadi tidak bisa dihapus.');
        }

        $material->delete();

        return redirect()->route('admin.materials.index')->with('status', 'Bahan dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Material $material = null): array
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50', Rule::unique('materials', 'sku')->ignore($material)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'opening_stock' => ['nullable', 'numeric'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $category = MaterialCategory::firstOrCreate(['slug' => Str::slug($data['category'])], ['name' => $data['category']]);
        unset($data['category']);

        return [
            ...$data,
            'material_category_id' => $category->id,
            'dimension_type' => 'count',
            'cost_price' => $data['cost_price'] ?? 0,
            'opening_stock' => $data['opening_stock'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
