<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Formula;
use App\Models\Item;
use App\Models\MotorOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Master data motor (kategori, brand, type, varian) untuk formula. */
class MotorOptionController extends Controller
{
    public function index(): View
    {
        $options = MotorOption::query()->ordered()->get()->groupBy('kind');
        $usage = collect(MotorOption::COLUMNS)->mapWithKeys(fn (string $column, string $kind) => [
            $kind => Formula::query()->whereNotNull($column)->selectRaw("{$column} as name, COUNT(*) as total")->groupBy($column)->pluck('total', 'name'),
        ]);

        return view('admin.motor-options.index', ['options' => $options, 'usage' => $usage]);
    }

    /** Tambah pilihan; dipakai juga oleh tombol "+" di form formula (JSON). */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(MotorOption::KINDS))],
            'name' => ['required', 'string', 'max:150'],
        ], ['name.required' => 'Isi namanya dulu.']);

        $name = MotorOption::remember($data['kind'], $data['name']);

        if ($request->expectsJson()) {
            return response()->json(['name' => $name, 'message' => MotorOption::KINDS[$data['kind']].' "'.$name.'" disimpan.']);
        }

        return back()->with('status', MotorOption::KINDS[$data['kind']].' "'.$name.'" disimpan.');
    }

    /** Ganti nama; formula & produk yang memakai nama lama ikut diperbarui. */
    public function update(Request $request, MotorOption $option): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('motor_options', 'name')->where('kind', $option->kind)->ignore($option)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], ['name.unique' => 'Nama itu sudah ada.']);

        DB::transaction(function () use ($option, $data) {
            $old = $option->name;
            $new = trim($data['name']);
            $column = MotorOption::COLUMNS[$option->kind];

            if ($old !== $new) {
                Formula::query()->where($column, $old)->update([$column => $new]);

                if ($option->kind === 'type') {
                    Item::query()->where('model_motor', $old)->update(['model_motor' => $new]);
                } elseif ($option->kind === 'varian') {
                    Item::query()->where('variant', $old)->update(['variant' => $new]);
                }
            }

            $option->update(['name' => $new, 'sort_order' => (int) ($data['sort_order'] ?? $option->sort_order)]);
        });

        return back()->with('status', 'Perubahan disimpan.');
    }

    public function destroy(MotorOption $option): RedirectResponse
    {
        if ($used = $option->usage()) {
            return back()->with('error', "\"{$option->name}\" masih dipakai {$used} formula, jadi tidak bisa dihapus.");
        }

        $option->delete();

        return back()->with('status', MotorOption::KINDS[$option->kind].' "'.$option->name.'" dihapus.');
    }
}
