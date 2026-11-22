<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CostComponent;
use App\Models\Formula;
use App\Models\FormulaMaterial;
use App\Models\Item;
use App\Models\Machine;
use App\Models\Material;
use App\Models\MotorcycleModel;
use App\Models\PriceTier;
use App\Models\Production;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Formula (HPP) — resep produksi lengkap seperti menu lama: bahan (dengan
 * ukuran potong, susut, dan sisa potong plat), proses kerja, pemakaian mesin,
 * lalu kartu HPP, harga jual per tingkatan, dan kapasitas dari stok.
 *
 * Kolom "merek" pada baris bahan berasal dari Resep BOM di Excel: baris
 * bermerek hanya dipotong untuk produk merek itu. Setiap simpan menghitung
 * ulang pemakaian bahan produksi lama, sama seperti Excel yang menghitung live.
 */
class FormulaController extends Controller
{
    public function index(Request $request): View
    {
        $formulas = Formula::query()
            ->with(['materials.material', 'costs.component', 'machines.machine', 'motorcycleModel'])
            ->withCount(['materials', 'costs', 'machines'])
            ->addSelect(['items_count' => Item::selectRaw('COUNT(*)')->whereColumn('items.formula_id', 'formulas.id')])
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
            ))
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->get();

        $tiers = PriceTier::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.formulas.index', [
            // HPP di daftar dihitung untuk merek pertama, bukan semua baris merek sekaligus.
            'formulas' => $formulas->map(fn (Formula $formula) => tap($formula->forBrand($formula->brands()->first()), function ($copy) use ($formula) {
                $copy->hpp_brand = $formula->brands()->first();
            })),
            // Kolom harga jual memakai tingkatan margin tertinggi tanpa potongan marketplace.
            'mainTier' => $tiers->where('fee_percent', 0)->sortByDesc('margin_percent')->first() ?? $tiers->first(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Formula(['output_qty' => 1, 'output_unit' => 'pcs', 'is_active' => true]));
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $formula = DB::transaction(function () use ($request) {
            $data = $this->validated($request);
            $formula = Formula::create($data['formula']);
            $this->syncLines($formula, $data);

            return $formula;
        });

        $inventory->syncFormula($formula);

        return redirect()->route('admin.formulas.edit', [$formula, 'tab' => 'hasil'])->with('status', 'Formula dibuat.');
    }

    public function edit(Request $request, Formula $formula): View
    {
        return $this->form($formula->load(['materials.material', 'costs.component', 'machines.machine']), $request->input('merek'));
    }

    public function update(Request $request, Formula $formula, InventoryService $inventory): RedirectResponse
    {
        DB::transaction(function () use ($request, $formula) {
            $data = $this->validated($request, $formula);
            $formula->update($data['formula']);
            $this->syncLines($formula, $data);
        });

        $inventory->syncFormula($formula);

        return redirect()->route('admin.formulas.edit', [$formula, 'tab' => $request->input('tab', 'hasil')])
            ->with('status', 'Formula disimpan. HPP dan stok bahan dihitung ulang.');
    }

    public function destroy(Formula $formula): RedirectResponse
    {
        if ($formula->productions()->exists()) {
            return back()->with('error', 'Formula sudah dipakai produksi, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }

        DB::transaction(function () use ($formula) {
            Item::where('formula_id', $formula->id)->update(['formula_id' => null]);
            $formula->materials()->delete();
            $formula->costs()->delete();
            $formula->machines()->delete();
            $formula->delete();
        });

        return redirect()->route('admin.formulas.index')->with('status', 'Formula dihapus.');
    }

    private function form(Formula $formula, ?string $brand = null): View
    {
        $brands = $formula->exists ? $formula->brands() : collect();
        $hppBrand = $brands->contains($brand) ? $brand : $brands->first();

        // Kolom "Knalpot diproduksi": qty produksi produk berformula ini dengan
        // merek yang cocok (baris tanpa merek = semua produk yang punya merek).
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
            'components' => CostComponent::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'machines' => Machine::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'motorcycleModels' => MotorcycleModel::with('brand')->orderBy('name')->get(),
            'brands' => Item::query()->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand'),
            'products' => $formula->exists ? Item::where('formula_id', $formula->id)->orderBy('sku')->get() : collect(),
            'formulaBrands' => $brands,
            'hppBrand' => $hppBrand,
            'hppFormula' => $formula->exists ? $formula->forBrand($hppBrand) : null,
        ]);
    }

    /**
     * @return array{formula: array<string, mixed>, materials: array<int, array<string, mixed>>, costs: array<int, array<string, mixed>>, machines: array<int, array<string, mixed>>}
     */
    private function validated(Request $request, ?Formula $formula = null): array
    {
        if (blank($request->input('code')) && filled($request->input('name'))) {
            $request->merge(['code' => Str::upper(Str::slug($request->input('name')))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('formulas', 'name')->ignore($formula)],
            'code' => ['required', 'string', 'max:64', Rule::unique('formulas', 'code')->ignore($formula)],
            'motorcycle_model_id' => ['nullable', 'exists:motorcycle_models,id'],
            'output_qty' => ['required', 'numeric', 'gt:0'],
            'output_unit' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],

            'materials' => ['array'],
            'materials.*.material_id' => ['nullable', 'exists:materials,id'],
            'materials.*.brand' => ['nullable', 'string', 'max:50'],
            'materials.*.bom_group' => ['nullable', Rule::in(array_keys(FormulaMaterial::BOM_GROUPS))],
            'materials.*.input_mode' => ['nullable', Rule::in(array_keys(FormulaMaterial::INPUT_MODES))],
            'materials.*.piece_length_mm' => ['nullable', 'numeric', 'min:0'],
            'materials.*.piece_width_mm' => ['nullable', 'numeric', 'min:0'],
            'materials.*.piece_diameter_mm' => ['nullable', 'numeric', 'min:0'],
            'materials.*.qty' => ['nullable', 'numeric', 'min:0'],
            'materials.*.piece_count' => ['nullable', 'numeric', 'min:0'],
            'materials.*.waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'materials.*.use_nesting' => ['nullable', 'boolean'],
            'materials.*.notes' => ['nullable', 'string', 'max:255'],

            'costs' => ['array'],
            'costs.*.cost_component_id' => ['nullable', 'exists:cost_components,id'],
            'costs.*.bom_group' => ['nullable', Rule::in(array_keys(FormulaMaterial::BOM_GROUPS))],
            'costs.*.minutes' => ['nullable', 'numeric', 'min:0'],
            'costs.*.qty' => ['nullable', 'numeric', 'min:0'],
            'costs.*.notes' => ['nullable', 'string', 'max:255'],

            'machines' => ['array'],
            'machines.*.machine_id' => ['nullable', 'exists:machines,id'],
            'machines.*.bom_group' => ['nullable', Rule::in(array_keys(FormulaMaterial::BOM_GROUPS))],
            'machines.*.minutes' => ['nullable', 'numeric', 'min:0'],
            'machines.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $materials = collect($data['materials'] ?? [])->filter(fn ($row) => ! empty($row['material_id']))->values();
        $costs = collect($data['costs'] ?? [])->filter(fn ($row) => ! empty($row['cost_component_id']))->values();
        $machines = collect($data['machines'] ?? [])->filter(fn ($row) => ! empty($row['machine_id']))->values();

        foreach (['materials' => [$materials, 'material_id', 'Bahan'], 'costs' => [$costs, 'cost_component_id', 'Proses'], 'machines' => [$machines, 'machine_id', 'Mesin']] as $key => [$rows, $column, $label]) {
            if ($rows->pluck($column)->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages([$key => "{$label} yang sama tercantum dua kali. Gabungkan jadi satu baris."]);
            }
        }

        return [
            'formula' => [
                'name' => $data['name'],
                'code' => $data['code'],
                'motorcycle_model_id' => $data['motorcycle_model_id'] ?? null,
                'output_qty' => $data['output_qty'],
                'output_unit' => $data['output_unit'],
                'notes' => $data['notes'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ],
            'materials' => $materials->all(),
            'costs' => $costs->all(),
            'machines' => $machines->all(),
        ];
    }

    /**
     * @param  array{materials: array<int, array<string, mixed>>, costs: array<int, array<string, mixed>>, machines: array<int, array<string, mixed>>}  $data
     */
    private function syncLines(Formula $formula, array $data): void
    {
        $formula->materials()->delete();
        $formula->costs()->delete();
        $formula->machines()->delete();

        foreach ($data['materials'] as $order => $row) {
            $formula->materials()->create([
                'material_id' => $row['material_id'],
                'brand' => ($row['brand'] ?? null) ?: null,
                'bom_group' => $row['bom_group'] ?? 'other',
                'input_mode' => $row['input_mode'] ?? 'direct',
                'piece_length_mm' => $row['piece_length_mm'] ?? null,
                'piece_width_mm' => $row['piece_width_mm'] ?? null,
                'piece_diameter_mm' => $row['piece_diameter_mm'] ?? null,
                'qty' => $row['qty'] ?? 0,
                'piece_count' => $row['piece_count'] ?? 1,
                'waste_percent' => $row['waste_percent'] ?? 0,
                'use_nesting' => (bool) ($row['use_nesting'] ?? false),
                'notes' => $row['notes'] ?? null,
                'sort_order' => $order,
            ]);
        }

        foreach ($data['costs'] as $order => $row) {
            $formula->costs()->create([
                'cost_component_id' => $row['cost_component_id'],
                'bom_group' => $row['bom_group'] ?? 'finishing',
                'minutes' => $row['minutes'] ?? 0,
                'qty' => $row['qty'] ?? 1,
                'notes' => $row['notes'] ?? null,
                'sort_order' => $order,
            ]);
        }

        foreach ($data['machines'] as $order => $row) {
            $formula->machines()->create([
                'machine_id' => $row['machine_id'],
                'bom_group' => $row['bom_group'] ?? 'other',
                'minutes' => $row['minutes'] ?? 0,
                'notes' => $row['notes'] ?? null,
                'sort_order' => $order,
            ]);
        }
    }
}
