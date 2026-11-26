<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Master Satuan. */
class UnitController extends Controller
{
    public function index(): View
    {
        return view('admin.units.index', ['units' => Unit::query()->ordered()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30', Rule::unique('units', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
        ], ['name.unique' => 'Satuan ini sudah ada.']);

        Unit::create($data + ['sort_order' => (int) Unit::max('sort_order') + 1]);

        return back()->with('status', 'Satuan ditambahkan.');
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $unit->update(['description' => $data['description'] ?? null, 'sort_order' => (int) ($data['sort_order'] ?? $unit->sort_order)]);

        return back()->with('status', 'Satuan disimpan.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->isInUse()) {
            return back()->with('error', "Satuan {$unit->name} masih dipakai item, jadi tidak bisa dihapus.");
        }

        $unit->delete();

        return back()->with('status', 'Satuan dihapus.');
    }
}
