<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\ExpenseType;
use App\Models\ListOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Sheet "Daftar": pilihan dropdown + kategori pengeluaran. */
class ListOptionController extends Controller
{
    public function index(): View
    {
        return view('admin.list-options.index', [
            'lists' => ListOption::LISTS,
            'options' => ListOption::query()->orderBy('sort_order')->orderBy('id')->get()->groupBy('list'),
            'categories' => ExpenseCategory::query()->withCount('expenses')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'list' => ['required', Rule::in(array_keys(ListOption::LISTS))],
            'value' => ['required', 'string', 'max:255', Rule::unique('list_options')->where('list', $request->input('list'))],
        ]);

        ListOption::create([...$data, 'sort_order' => (int) ListOption::where('list', $data['list'])->max('sort_order') + 1]);

        return back()->with('status', "Pilihan \"{$data['value']}\" ditambahkan.");
    }

    public function destroy(ListOption $listOption): RedirectResponse
    {
        $listOption->delete();

        return back()->with('status', "Pilihan \"{$listOption->value}\" dihapus.");
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:expense_categories,name']]);
        $type = ExpenseType::firstOrCreate(['slug' => 'usaha-knalpot'], ['name' => 'Usaha Knalpot']);

        ExpenseCategory::create([
            'expense_type_id' => $type->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
        ]);

        return back()->with('status', "Kategori \"{$data['name']}\" ditambahkan.");
    }

    public function destroyCategory(ExpenseCategory $category): RedirectResponse
    {
        if ($category->expenses()->exists()) {
            return back()->with('error', 'Kategori sudah dipakai pengeluaran, jadi tidak bisa dihapus.');
        }

        $category->delete();

        return back()->with('status', 'Kategori dihapus.');
    }
}
