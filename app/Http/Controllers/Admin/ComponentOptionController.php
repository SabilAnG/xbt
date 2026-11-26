<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComponentOption;
use App\Models\FormulaComponent;
use App\Models\FormulaPart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Master Data Komponen: bagian formula & komponen per bagian. */
class ComponentOptionController extends Controller
{
    public function index(): View
    {
        $usage = FormulaComponent::query()->join('formula_parts', 'formula_parts.id', '=', 'formula_components.formula_part_id')
            ->selectRaw('LOWER(formula_parts.name) as part, LOWER(formula_components.name) as component, COUNT(DISTINCT formula_parts.formula_id) as total')
            ->groupBy('part', 'component')->get()
            ->mapWithKeys(fn ($row) => [$row->part.'|'.$row->component => (int) $row->total]);

        return view('admin.component-options.index', [
            'parts' => ComponentOption::query()->parts()->with('components')->get(),
            'usage' => $usage,
            'defaults' => ['' => 'Tidak otomatis', 'knalpot' => 'Otomatis di formula knalpot', 'setengah_jadi' => 'Otomatis di formula setengah jadi'],
        ]);
    }

    /** Tambah bagian atau komponen; tombol "+" di form formula memakai JSON. */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in([ComponentOption::PART, ComponentOption::COMPONENT])],
            'name' => ['required', 'string', 'max:100'],
            'part' => ['required_if:kind,komponen', 'nullable', 'string', 'max:100'],
        ], ['name.required' => 'Isi namanya dulu.', 'part.required_if' => 'Isi nama bagiannya dulu.']);

        if ($data['kind'] === ComponentOption::PART) {
            $name = ComponentOption::rememberPart($data['name'])->name;
            $message = 'Bagian "'.$name.'" disimpan.';
        } else {
            $name = ComponentOption::rememberComponent($data['part'], $data['name']);
            $message = 'Komponen "'.$name.'" disimpan ke bagian '.ComponentOption::findPart($data['part'])->name.'.';
        }

        return $request->expectsJson()
            ? response()->json(['name' => $name, 'message' => $message])
            : back()->with('status', $message);
    }

    /** Ganti nama / urutan / bawaan; formula yang memakai nama lama ikut diperbarui. */
    public function update(Request $request, ComponentOption $option): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100',
                Rule::unique('component_options', 'name')->where('kind', $option->kind)->where('parent_id', $option->parent_id)->ignore($option)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'default_for' => ['nullable', Rule::in(['knalpot', 'setengah_jadi'])],
        ], ['name.unique' => 'Nama itu sudah ada.']);

        DB::transaction(function () use ($option, $data, $request) {
            $old = $option->name;
            $new = trim($data['name']);

            if ($old !== $new) {
                if ($option->kind === ComponentOption::PART) {
                    FormulaPart::query()->where('name', $old)->update(['name' => $new]);
                } else {
                    FormulaComponent::query()->where('name', $old)
                        ->whereHas('part', fn ($query) => $query->where('name', $option->part->name))
                        ->update(['name' => $new]);
                }
            }

            $changes = ['name' => $new, 'sort_order' => (int) ($data['sort_order'] ?? $option->sort_order)];
            if ($option->kind === ComponentOption::PART && $request->has('default_for')) {
                $changes['default_for'] = $data['default_for'] ?? null;
            }

            $option->update($changes);
        });

        return back()->with('status', 'Perubahan disimpan.');
    }

    /** Hanya menghapus saran; formula yang sudah ada tidak berubah. */
    public function destroy(ComponentOption $option): RedirectResponse
    {
        $label = $option->kind === ComponentOption::PART ? 'Bagian' : 'Komponen';
        $option->delete();

        return back()->with('status', $label.' "'.$option->name.'" dihapus.');
    }
}
