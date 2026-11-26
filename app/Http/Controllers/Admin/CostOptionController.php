<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CostOption;
use App\Models\FormulaCost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Master Data Ongkos untuk formula. */
class CostOptionController extends Controller
{
    public function index(): View
    {
        $usage = FormulaCost::query()->selectRaw('LOWER(name) as name, COUNT(DISTINCT formula_id) as total')->groupByRaw('LOWER(name)')->pluck('total', 'name');

        return view('admin.cost-options.index', ['options' => CostOption::query()->ordered()->get(), 'usage' => $usage]);
    }

    /** Tambah ongkos; tombol "+" di form formula memakai JSON. */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_mode' => ['nullable', Rule::in(array_keys(CostOption::PAYMENT_MODES))],
        ], ['name.required' => 'Isi nama ongkosnya dulu.']);

        $existing = CostOption::findByName($data['name']);
        $option = CostOption::remember($data['name'], (float) ($data['amount'] ?? 0));
        if (! $existing && ! empty($data['payment_mode'])) {
            $option->update(['payment_mode' => $data['payment_mode']]);
        }
        $message = $existing ? "Ongkos \"{$option->name}\" sudah ada di Data Ongkos." : "Ongkos \"{$option->name}\" disimpan ke Data Ongkos.";

        return $request->expectsJson()
            ? response()->json(['name' => $option->name, 'amount' => (float) $option->amount, 'message' => $message])
            : back()->with('status', $message);
    }

    /** Ganti nama/tarif bawaan; nama di formula ikut berubah (nominal per formula tetap). */
    public function update(Request $request, CostOption $option): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('cost_options', 'name')->ignore($option)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'payment_mode' => ['nullable', Rule::in(array_keys(CostOption::PAYMENT_MODES))],
        ], ['name.unique' => 'Nama ongkos itu sudah ada.']);

        DB::transaction(function () use ($option, $data) {
            $new = trim($data['name']);

            if ($option->name !== $new) {
                FormulaCost::query()->where('name', $option->name)->update(['name' => $new]);
            }

            $option->update([
                'name' => $new,
                'amount' => (float) ($data['amount'] ?? 0),
                'sort_order' => (int) ($data['sort_order'] ?? $option->sort_order),
                'payment_mode' => $data['payment_mode'] ?? $option->payment_mode,
            ]);
        });

        return back()->with('status', 'Perubahan disimpan.');
    }

    /** Hanya menghapus dari daftar; formula yang sudah ada tidak berubah. */
    public function destroy(CostOption $option): RedirectResponse
    {
        $option->delete();

        return back()->with('status', "Ongkos \"{$option->name}\" dihapus dari daftar.");
    }
}
