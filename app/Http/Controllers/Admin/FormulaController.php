<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Formula;
use App\Models\FormulaComponent;
use App\Models\FormulaCost;
use App\Models\Item;
use App\Models\Material;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Formula Knalpot: bagian (Leheran, Silincer) → komponen (Pureng, Plendes,
 * P1, …) → bahan dari gudang, lengkap dengan harga yang terbawa otomatis.
 */
class FormulaController extends Controller
{
    /** Saran nama komponen per bagian. */
    public const COMPONENT_SUGGESTIONS = [
        'Leheran' => ['Pureng', 'Plendes', 'P1', 'P2', 'P3', 'P4', 'P5', 'Sok sambungan', 'Breket leher'],
        'Silincer' => ['Tabung', 'Sarangan', 'Tutup depan', 'Tutup belakang', 'Moncong', 'Gaswool', 'Breket silincer', 'Tameng'],
        'Aksesoris' => ['Baut', 'Ring', 'Stiker / emblem', 'Tameng', 'Karet', 'Packing', 'Dus'],
        'Bahan' => ['P1', 'P2', 'P3', 'P4', 'Plendes', 'Pureng'],
    ];

    public function index(Request $request): View
    {
        $formulas = Formula::query()->with(['item', 'outputMaterial', 'parts.components'])
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('motor_type', 'like', "%{$search}%")
            ))
            ->when($request->input('kategori'), fn ($query, $category) => $query->where('motor_category', $category))
            ->orderBy('motor_category')->orderBy('name')
            ->get();

        return view('admin.formulas.index', ['formulas' => $formulas]);
    }

    public function show(Formula $formula): View
    {
        $formula->loadStructure()->load('item', 'outputMaterial');

        return view('admin.formulas.show', [
            'formula' => $formula,
            'requirements' => $formula->requirements(),
        ]);
    }

    public function create(Request $request): View
    {
        $output = $request->input('jenis') === 'setengah-jadi' ? Formula::SEMI_FINISHED : Formula::KNALPOT;
        $formula = new Formula(['motor_category' => 'matic', 'output_type' => $output, 'is_active' => true]);
        $formula->setRelation('parts', collect());
        $formula->setRelation('costs', collect());

        return $this->form($formula);
    }

    public function store(Request $request): RedirectResponse
    {
        $formula = DB::transaction(function () use ($request) {
            [$data, $parts, $costs] = $this->validated($request);
            $formula = Formula::create($data);
            $this->saveStructure($formula, $parts, $costs);
            $this->linkOutput($formula, $request);

            return $formula;
        });

        return redirect()->route('admin.formulas.show', $formula)->with('status', 'Formula disimpan.');
    }

    public function edit(Formula $formula): View
    {
        return $this->form($formula->loadStructure());
    }

    public function update(Request $request, Formula $formula): RedirectResponse
    {
        DB::transaction(function () use ($request, $formula) {
            [$data, $parts, $costs] = $this->validated($request, $formula);
            $formula->update($data);
            $formula->parts()->delete();
            $formula->costs()->delete();
            $this->saveStructure($formula, $parts, $costs);
            $this->linkOutput($formula, $request);
        });

        return redirect()->route('admin.formulas.show', $formula)->with('status', 'Formula disimpan.');
    }

    public function destroy(Formula $formula): RedirectResponse
    {
        if ($formula->productions()->exists() || DB::table('production_plan_lines')->where('formula_id', $formula->id)->exists()) {
            return back()->with('error', 'Formula sudah dipakai di rencana atau produksi, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }

        $formula->delete();

        return redirect()->route('admin.formulas.index')->with('status', 'Formula dihapus.');
    }

    /** Salin formula untuk varian baru (mis. Mio → Beat). */
    public function duplicate(Formula $formula): RedirectResponse
    {
        $copy = DB::transaction(function () use ($formula) {
            $formula->loadStructure();
            $code = $formula->code.'-SALIN';

            for ($i = 2; Formula::where('code', $code)->exists(); $i++) {
                $code = $formula->code.'-SALIN'.$i;
            }

            $copy = $formula->replicate(['item_id', 'output_material_id']);
            $copy->fill(['code' => $code, 'name' => $formula->name.' (salinan)'])->save();

            foreach ($formula->parts as $part) {
                $newPart = $copy->parts()->create($part->only('name', 'sort_order'));

                foreach ($part->components as $component) {
                    $newComponent = $newPart->components()->create($component->only('name', 'qty', 'sort_order'));
                    $newComponent->materials()->createMany($component->materials->map->only('material_id', 'qty', 'sort_order')->all());
                }
            }

            $copy->costs()->createMany($formula->costs->map->only('name', 'amount', 'sort_order')->all());

            return $copy;
        });

        return redirect()->route('admin.formulas.edit', $copy)->with('status', 'Formula disalin. Ubah nama dan isinya, lalu simpan.');
    }

    private function form(Formula $formula): View
    {
        $materials = Material::query()->with(['category', 'warehouse'])
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $formula->parts->flatMap->components->flatMap->materials->pluck('material_id')))
            ->orderBy('name')->get();

        $parts = old('parts', $formula->parts->map(fn ($part) => [
            'name' => $part->name,
            'components' => $part->components->map(fn (FormulaComponent $component) => [
                'name' => $component->name,
                'qty' => (float) $component->qty,
                'materials' => $component->materials->map(fn ($line) => [
                    'material_id' => $line->material_id,
                    'qty' => (float) $line->qty,
                ])->all(),
            ])->all(),
        ])->all());

        if ($parts === []) {
            $parts = collect($formula->isSemiFinished() ? ['Bahan'] : Formula::DEFAULT_PARTS)->map(fn (string $name) => ['name' => $name, 'components' => []])->all();
        }

        $costs = old('costs', $formula->costs->map(fn ($cost) => [
            'name' => $cost->name,
            'amount' => (float) $cost->amount,
        ])->all());

        return view('admin.formulas.form', [
            'formula' => $formula,
            'parts' => array_values($parts),
            'costs' => array_values($costs),
            'materials' => $materials->map(fn (Material $material) => [
                'id' => $material->id,
                'label' => $material->label(),
                'code' => $material->code,
                'warehouse' => $material->warehouse->type,
                'category' => $material->category?->name ?? 'Lainnya',
                'pipe' => $material->isPipe(),
                'unit' => $material->stockUnit(),
                'cost' => round($material->unitCost(), 4),
                'bar' => (float) $material->bar_length_cm,
                'stock' => (float) $material->stock,
            ])->values(),
            'warehouses' => collect([Warehouse::RAW, Warehouse::SEMI, Warehouse::ACCESSORY])->mapWithKeys(fn (string $type) => [$type => Warehouse::forType($type)->name]),
            'costSuggestions' => collect(['Ongkos las', 'Ongkos bending', 'Finishing / poles', 'Packing'])
                ->merge(FormulaCost::query()->distinct()->pluck('name'))->unique()->values(),
            'suggestions' => collect(self::COMPONENT_SUGGESTIONS)
                ->map(fn (array $names, string $part) => collect($names)->merge(
                    FormulaComponent::query()->whereHas('part', fn ($query) => $query->where('name', $part))->distinct()->pluck('name')
                )->unique()->values()),
            'semiMaterials' => Material::query()->where('measure', Material::UNIT)
                ->where('warehouse_id', Warehouse::forType(Warehouse::SEMI)->id)
                ->whereNotIn('id', Formula::query()->whereNotNull('output_material_id')->whereKeyNot($formula->id ?? 0)->pluck('output_material_id'))
                ->orderBy('name')->get()->mapWithKeys(fn (Material $material) => [$material->id => $material->label()]),
            'items' => Item::query()->where('is_active', true)
                ->whereDoesntHave('formula', fn ($query) => $query->whereKeyNot($formula->id ?? 0))
                ->orderBy('name')->pluck('name', 'id'),
            'motorTypes' => Formula::query()->whereNotNull('motor_type')->distinct()->pluck('motor_type')
                ->merge(Formula::MOTOR_TYPES)->unique()->values(),
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}
     */
    private function validated(Request $request, ?Formula $formula = null): array
    {
        $request->merge(['parts' => $this->withoutBlankRows($request->input('parts', []))]);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('formulas', 'code')->ignore($formula)],
            'name' => ['required', 'string', 'max:255'],
            'motor_category' => ['required', Rule::in(array_keys(Formula::MOTOR_CATEGORIES))],
            'output_type' => ['nullable', Rule::in(array_keys(Formula::OUTPUTS))],
            'output_material_id' => ['nullable', Rule::exists('materials', 'id')->where('measure', Material::UNIT)],
            'motor_type' => [$request->input('output_type') === Formula::SEMI_FINISHED ? 'nullable' : 'required', 'string', 'max:100'],
            'variant' => ['nullable', 'string', 'max:150'],
            'item_id' => ['nullable', 'exists:items,id'],
            'notes' => ['nullable', 'string'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.name' => ['required', 'string', 'max:100'],
            'parts.*.components' => ['array'],
            'parts.*.components.*.name' => ['required', 'string', 'max:100'],
            'parts.*.components.*.qty' => ['required', 'numeric', 'gt:0'],
            'parts.*.components.*.materials' => ['array'],
            'parts.*.components.*.materials.*.material_id' => ['required', 'exists:materials,id'],
            'parts.*.components.*.materials.*.qty' => ['required', 'numeric', 'gt:0'],
            'costs' => ['array'],
            'costs.*.name' => ['nullable', 'string', 'max:100'],
            'costs.*.amount' => ['nullable', 'numeric', 'min:0'],
        ], [
            'parts.required' => 'Formula butuh minimal satu bagian, mis. Leheran.',
            'parts.*.name.required' => 'Isi nama bagian.',
            'parts.*.components.*.name.required' => 'Isi nama komponen.',
            'parts.*.components.*.qty.gt' => 'Jumlah komponen harus lebih dari 0.',
            'parts.*.components.*.materials.*.qty.required' => 'Isi kebutuhan bahan.',
            'parts.*.components.*.materials.*.qty.gt' => 'Kebutuhan bahan harus lebih dari 0.',
        ]);

        $costs = collect($data['costs'] ?? [])
            ->filter(fn (array $cost) => filled($cost['name'] ?? null))
            ->map(fn (array $cost) => ['name' => trim($cost['name']), 'amount' => (float) ($cost['amount'] ?? 0)])
            ->values()->all();

        $parts = array_values($data['parts']);
        unset($data['parts'], $data['costs'], $data['item_id'], $data['output_material_id']);

        $data['output_type'] = ($data['output_type'] ?? null) ?: Formula::KNALPOT;
        $data['code'] = ($data['code'] ?? null) ?: $this->nextCode();
        $data['is_active'] = $request->boolean('is_active', true);

        return [$data, $parts, $costs];
    }

    /**
     * Baris bahan tanpa barang dan komponen tanpa nama & bahan dibuang dulu,
     * supaya baris kosong di form tidak memicu galat.
     *
     * @param  array<int, mixed>  $parts
     * @return array<int, mixed>
     */
    private function withoutBlankRows(array $parts): array
    {
        return collect($parts)->map(function ($part) {
            $components = collect($part['components'] ?? [])->map(function ($component) {
                $component['materials'] = collect($component['materials'] ?? [])
                    ->filter(fn ($line) => filled($line['material_id'] ?? null) || filled($line['qty'] ?? null))
                    ->values()->all();

                return $component;
            })->filter(fn ($component) => filled($component['name'] ?? null) || $component['materials'] !== [])->values()->all();

            return ['name' => $part['name'] ?? null, 'components' => $components];
        })->filter(fn ($part) => filled($part['name']) || $part['components'] !== [])->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @param  list<array<string, mixed>>  $costs
     */
    private function saveStructure(Formula $formula, array $parts, array $costs): void
    {
        foreach ($parts as $partOrder => $part) {
            $newPart = $formula->parts()->create(['name' => trim($part['name']), 'sort_order' => $partOrder]);

            foreach (array_values($part['components'] ?? []) as $componentOrder => $component) {
                $newComponent = $newPart->components()->create([
                    'name' => trim($component['name']), 'qty' => (float) $component['qty'], 'sort_order' => $componentOrder,
                ]);

                foreach (array_values($component['materials'] ?? []) as $lineOrder => $line) {
                    $newComponent->materials()->create([
                        'material_id' => (int) $line['material_id'], 'qty' => (float) $line['qty'], 'sort_order' => $lineOrder,
                    ]);
                }
            }
        }

        foreach ($costs as $order => $cost) {
            $formula->costs()->create($cost + ['sort_order' => $order]);
        }
    }

    /**
     * Hubungkan ke hasilnya: produk jadi untuk knalpot, atau barang di Gudang
     * Setengah Jadi untuk formula setengah jadi. Bila belum dipilih, dibuat
     * otomatis dari nama formula.
     */
    private function linkOutput(Formula $formula, Request $request): void
    {
        if ($formula->isSemiFinished()) {
            $formula->forceFill(['item_id' => null])->save();
            $this->linkSemiMaterial($formula, $request->input('output_material_id'));

            return;
        }

        $formula->forceFill(['output_material_id' => null])->save();
        $this->linkItem($formula, $request);
    }

    private function linkSemiMaterial(Formula $formula, mixed $materialId): void
    {
        if (filled($materialId)) {
            $formula->forceFill(['output_material_id' => (int) $materialId])->save();

            return;
        }

        if ($formula->output_material_id) {
            return;
        }

        $next = Material::where('code', 'like', 'SJ-%')->count() + 1;

        do {
            $code = sprintf('SJ-%03d', $next++);
        } while (Material::where('code', $code)->exists());

        $material = Material::create([
            'code' => $code, 'name' => $formula->name, 'warehouse_id' => Warehouse::forType(Warehouse::SEMI)->id,
            'measure' => Material::UNIT, 'unit' => 'Pcs', 'is_active' => true,
            'notes' => 'Dibuat dari formula '.$formula->code.'.',
        ]);
        $formula->forceFill(['output_material_id' => $material->id])->save();
    }

    /** Hubungkan ke produk jadi yang dipilih, atau buat produk baru dari formula. */
    private function linkItem(Formula $formula, Request $request): void
    {
        $itemId = $request->input('item_id');

        if (filled($itemId)) {
            $formula->forceFill(['item_id' => (int) $itemId])->save();

            return;
        }

        if ($formula->item_id) {
            $formula->item->update(['name' => $formula->name, 'model_motor' => $formula->motor_type, 'variant' => $formula->variant]);

            return;
        }

        $sku = $formula->code;

        for ($i = 2; Item::where('sku', $sku)->exists(); $i++) {
            $sku = $formula->code.'-'.$i;
        }

        $item = Item::create([
            'sku' => $sku, 'name' => $formula->name, 'model_motor' => $formula->motor_type, 'variant' => $formula->variant,
            'unit' => 'pcs', 'is_active' => true,
        ]);
        $formula->forceFill(['item_id' => $item->id])->save();
    }

    private function nextCode(): string
    {
        $next = Formula::count() + 1;

        do {
            $code = sprintf('FRM-%03d', $next++);
        } while (Formula::where('code', $code)->exists());

        return $code;
    }
}
