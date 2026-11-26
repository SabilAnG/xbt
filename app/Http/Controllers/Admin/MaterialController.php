<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormulaComponentMaterial;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PipePiece;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Master Bahan: barang di Gudang Bahan Baku & Gudang Setengah Jadi. */
class MaterialController extends Controller
{
    public function index(Request $request): View
    {
        $materials = Material::query()->with(['category', 'warehouse', 'stocks.warehouse'])
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('spec', 'like', "%{$search}%")
            ))
            ->when($request->input('warehouse'), fn ($query, $id) => $query->where('warehouse_id', $id))
            ->when($request->input('category'), fn ($query, $id) => $query->where('material_category_id', $id))
            ->orderBy('warehouse_id')->orderBy('name')
            ->get();

        return view('admin.materials.index', [
            'materials' => $materials,
            'warehouses' => Warehouse::storage(),
            'categories' => MaterialCategory::orderBy('sort_order')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(Material $material): View
    {
        $material->load(['category', 'warehouse', 'stocks.warehouse']);

        return view('admin.materials.show', [
            'material' => $material,
            'pieces' => $material->isPipe()
                ? $material->pieces()->with('warehouse')->where('status', PipePiece::AVAILABLE)->orderBy('warehouse_id')->orderBy('length_cm')->get()
                : collect(),
            'movements' => $material->movements()->with('warehouse', 'piece')->latest('moved_at')->latest('id')->paginate(25),
            'usedIn' => FormulaComponentMaterial::query()->with('component.part.formula')->where('material_id', $material->id)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $warehouse = Warehouse::forType($request->input('gudang', Warehouse::RAW));

        return $this->form(new Material([
            'warehouse_id' => $warehouse->id,
            'measure' => $request->input('jenis') === 'pipa' ? Material::LENGTH : Material::UNIT,
            'unit' => $request->input('jenis') === 'pipa' ? 'Batang' : 'Pcs',
            'bar_length_cm' => 600,
            'min_reusable_cm' => 5,
            'is_active' => true,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $material = Material::create($this->validated($request));

        return redirect()->route('admin.materials.show', $material)->with('status', 'Bahan ditambahkan.');
    }

    public function edit(Material $material): View
    {
        return $this->form($material);
    }

    public function update(Request $request, Material $material): RedirectResponse
    {
        $data = $this->validated($request, $material);

        $unitsChanged = $data['use_unit'] !== $material->use_unit || (float) $data['conversion'] !== (float) $material->conversion;

        if ($material->movements()->exists() && ($data['measure'] !== $material->measure || (int) $data['warehouse_id'] !== $material->warehouse_id || $unitsChanged)) {
            return back()->withInput()->with('error', 'Item sudah punya stok, jadi cara hitung, gudang asal, satuan pakai, dan konversinya tidak bisa diubah.');
        }

        $material->update($data);

        return redirect()->route('admin.materials.show', $material)->with('status', 'Bahan disimpan.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        if ($material->movements()->exists() || FormulaComponentMaterial::where('material_id', $material->id)->exists()) {
            return back()->with('error', 'Bahan sudah punya stok atau dipakai di formula, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }

        $material->delete();

        return redirect()->route('admin.master-items.index')->with('status', 'Item dihapus.');
    }

    private function form(Material $material): View
    {
        return view('admin.materials.form', [
            'material' => $material,
            'warehouses' => Warehouse::storage()->pluck('name', 'id'),
            'categories' => MaterialCategory::orderBy('sort_order')->orderBy('name')->pluck('name'),
            'units' => Unit::names()->whenEmpty(fn () => collect(['Pcs'])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Material $material = null): array
    {
        $isPipe = $request->input('measure') === Material::LENGTH;

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('materials', 'code')->ignore($material)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'warehouse_id' => ['required', Rule::in(Warehouse::storage()->pluck('id')->all())],
            'measure' => ['required', Rule::in(array_keys(Material::MEASURES))],
            'unit' => [$isPipe ? 'nullable' : 'required', 'string', 'max:30', ...($isPipe ? [] : [Rule::in(Unit::names()->all())])],
            'use_unit' => ['nullable', 'string', Rule::in(Unit::names()->all())],
            'conversion' => ['nullable', 'numeric', 'gt:0'],
            'spec' => ['nullable', 'string', 'max:255'],
            'bar_length_cm' => [$isPipe ? 'required' : 'nullable', 'numeric', 'gt:0'],
            'min_reusable_cm' => ['nullable', 'numeric', 'min:0'],
            'last_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ], [
            'bar_length_cm.required' => 'Isi panjang satu batang pipa.',
            'warehouse_id.in' => 'Pilih Gudang Bahan Baku, Setengah Jadi, atau Aksesoris.',
        ]);

        $data['material_category_id'] = filled($data['category'] ?? null)
            ? MaterialCategory::firstOrCreate(['name' => trim($data['category'])])->id
            : null;
        unset($data['category']);

        if (blank($data['code'] ?? null)) {
            $warehouse = Warehouse::find($data['warehouse_id']);
            $prefix = match (true) {
                $warehouse?->type === Warehouse::SEMI => 'SJ',
                $warehouse?->type === Warehouse::ACCESSORY => 'AK',
                $isPipe => 'PP',
                default => 'BB',
            };
            $next = Material::where('code', 'like', $prefix.'-%')->count() + 1;

            do {
                $data['code'] = sprintf('%s-%03d', $prefix, $next++);
            } while (Material::where('code', $data['code'])->exists());
        }

        $data['unit'] = $isPipe ? 'Batang' : $data['unit'];
        $hasConversion = ! $isPipe && filled($data['use_unit'] ?? null) && $data['use_unit'] !== $data['unit'];

        if ($hasConversion && (float) ($data['conversion'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['conversion' => 'Isi berapa '.$data['use_unit'].' dalam 1 '.$data['unit'].'.']);
        }

        $data['use_unit'] = $hasConversion ? $data['use_unit'] : null;
        $data['conversion'] = $hasConversion ? (float) $data['conversion'] : 1;
        $data['bar_length_cm'] = $isPipe ? (float) $data['bar_length_cm'] : null;
        $data['min_reusable_cm'] = $isPipe ? (float) ($data['min_reusable_cm'] ?? 0) : 0;
        $data['last_price'] = (float) ($data['last_price'] ?? 0);
        $data['min_stock'] = (float) ($data['min_stock'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
