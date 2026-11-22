<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Item;
use App\Services\DashboardSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Master merek produk beserta ringkasan stok & penjualan per merek. */
class BrandController extends Controller
{
    public function index(DashboardSummary $summary): View
    {
        return view('admin.brands.index', [
            'brands' => Brand::query()->ordered()->get(),
            'stats' => $summary->byBrand()->keyBy('name'),
            'unbranded' => Item::query()->where(fn ($q) => $q->whereNull('brand')->orWhere('brand', ''))->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.brands.form', ['brand' => new Brand(['is_active' => true, 'color' => '#ea580c', 'sort_order' => (int) Brand::max('sort_order') + 1])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Brand::create($this->validated($request));

        return redirect()->route('admin.brands.index')->with('status', 'Merek ditambahkan. Pilih merek ini di form produk dan di baris resep.');
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.form', ['brand' => $brand]);
    }

    /** Ganti nama ikut memperbarui produk & baris resep yang memakai merek ini. */
    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $this->validated($request, $brand);

        DB::transaction(function () use ($brand, $data) {
            $brand->rename($data['name']);
            $brand->update(collect($data)->except('name')->all());
        });

        return redirect()->route('admin.brands.index')->with('status', "Merek {$brand->name} disimpan.");
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->isInUse()) {
            return back()->with('error', "Merek {$brand->name} masih dipakai produk atau resep. Nonaktifkan saja.");
        }

        $brand->delete();

        return back()->with('status', 'Merek dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Brand $brand = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'not_regex:/,/', Rule::unique('brands', 'name')->ignore($brand)],
            'code' => ['nullable', 'string', 'max:20'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], ['name.not_regex' => 'Nama merek tidak boleh berisi koma.']);

        $data['name'] = trim($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
