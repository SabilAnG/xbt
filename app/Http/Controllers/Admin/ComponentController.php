<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Master Komponen: bagian knalpot beserta bahan baku penyusunnya. */
class ComponentController extends Controller
{
    public function index(Request $request): View
    {
        $components = Component::query()->with('materials.material')
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
            ))
            ->when($request->input('group'), fn ($query, $group) => $query->where('group', $group))
            ->orderBy('sort_order')->orderBy('code')
            ->get();

        return view('admin.components.index', ['parts' => $components]);
    }

    public function show(Component $component): View
    {
        return view('admin.components.show', [
            'part' => $component->load('materials.material'),
            'movements' => $component->movements()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Component(['unit' => 'Pcs', 'group' => 'leher', 'is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $component = DB::transaction(function () use ($request) {
            [$data, $lines] = $this->validated($request);
            $component = Component::create($data);
            $component->materials()->createMany($lines);

            return $component;
        });

        return redirect()->route('admin.components.show', $component)->with('status', 'Komponen ditambahkan.');
    }

    public function edit(Component $component): View
    {
        return $this->form($component->load('materials'));
    }

    public function update(Request $request, Component $component): RedirectResponse
    {
        DB::transaction(function () use ($request, $component) {
            [$data, $lines] = $this->validated($request, $component);
            $component->update($data);
            $component->materials()->delete();
            $component->materials()->createMany($lines);
        });

        return redirect()->route('admin.components.show', $component)->with('status', 'Komponen disimpan.');
    }

    public function destroy(Component $component): RedirectResponse
    {
        if ($component->movements()->exists()) {
            return back()->with('error', 'Komponen sudah punya mutasi stok, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }

        $component->delete();

        return redirect()->route('admin.components.index')->with('status', 'Komponen dihapus.');
    }

    private function form(Component $component): View
    {
        return view('admin.components.form', [
            'part' => $component,
            'materials' => Material::query()->where('is_active', true)->orWhereIn('id', $component->materials->pluck('material_id'))
                ->orderBy('name')->get(['id', 'name', 'unit', 'cost_price']),
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array{material_id: int, qty: float}>}
     */
    private function validated(Request $request, ?Component $component = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('components', 'code')->ignore($component)],
            'name' => ['required', 'string', 'max:255'],
            'group' => ['required', Rule::in(array_keys(Component::GROUPS))],
            'unit' => ['required', 'string', 'max:30'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['array'],
            'lines.*.material_id' => ['nullable', 'exists:materials,id', 'distinct'],
            'lines.*.qty' => ['nullable', 'numeric', 'gt:0', 'required_with:lines.*.material_id'],
        ], [
            'lines.*.material_id.distinct' => 'Bahan yang sama dipilih dua kali.',
            'lines.*.qty.required_with' => 'Isi jumlah bahan.',
            'lines.*.qty.gt' => 'Jumlah bahan harus lebih dari 0.',
        ]);

        $lines = collect($data['lines'] ?? [])
            ->filter(fn (array $line) => filled($line['material_id'] ?? null))
            ->map(fn (array $line) => ['material_id' => (int) $line['material_id'], 'qty' => (float) $line['qty']])
            ->values()->all();

        unset($data['lines']);
        $data['min_stock'] = (float) ($data['min_stock'] ?? 0);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return [$data, $lines];
    }
}
