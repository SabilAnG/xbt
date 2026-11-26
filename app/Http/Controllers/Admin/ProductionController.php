<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Formula;
use App\Models\Production;
use App\Models\ProductionReceipt;
use App\Models\Supplier;
use App\Services\DocumentNumber;
use App\Services\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Nota produksi: dibuat sebagai draf (pratinjau rencana potong), lalu
 * dibukukan supaya stok bahan & knalpot jadi bergerak.
 */
class ProductionController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.productions.index', [
            'productions' => Production::query()->with('formula', 'item', 'planLine.plan')
                ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
                ->when($request->input('mode'), fn ($query, $mode) => $query->where('mode', $mode))
                ->when($request->input('rencana'), fn ($query, $plan) => $query->whereHas('planLine', fn ($query) => $query->where('production_plan_id', $plan)))
                ->latest('produced_at')->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function show(Production $production, ProductionService $service): View
    {
        $production->load('formula.item', 'formula.outputMaterial', 'item', 'planLine.plan', 'usages.material', 'cuts.piece.material', 'receipts.bill', 'serviceBills');

        return view('admin.productions.show', [
            'production' => $production,
            'preview' => $production->isPosted() ? null : $service->preview($production),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(new Production([
            'produced_at' => now(),
            'formula_id' => $request->integer('formula') ?: null,
            'qty' => 1,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $formula = Formula::findOrFail($data['formula_id']);

        $production = Production::create($data + [
            'production_number' => DocumentNumber::next('productions', Carbon::parse($data['produced_at'])),
            'item_id' => $formula->isSemiFinished() ? null : $formula->item_id,
            'status' => Production::DRAFT,
        ]);

        return redirect()->route('admin.productions.show', $production)->with('status', 'Draf produksi dibuat. Periksa rencana potong, lalu bukukan.');
    }

    public function edit(Production $production): View|RedirectResponse
    {
        if ($production->isPosted()) {
            return redirect()->route('admin.productions.show', $production)->with('error', 'Produksi yang sudah dibukukan harus dibatalkan dulu sebelum diubah.');
        }

        return $this->form($production);
    }

    public function update(Request $request, Production $production): RedirectResponse
    {
        if ($production->isPosted()) {
            return back()->with('error', 'Produksi yang sudah dibukukan harus dibatalkan dulu sebelum diubah.');
        }

        $data = $this->validated($request);
        $formula = Formula::findOrFail($data['formula_id']);
        $production->update($data + ['item_id' => $formula->isSemiFinished() ? null : $formula->item_id]);

        return redirect()->route('admin.productions.show', $production)->with('status', 'Draf produksi disimpan.');
    }

    public function destroy(Production $production): RedirectResponse
    {
        if ($production->isPosted()) {
            return back()->with('error', 'Batalkan pembukuan produksi ini dulu sebelum menghapusnya.');
        }

        $plan = $production->planLine?->plan;
        $production->delete();
        $plan?->refreshStatus();

        return redirect()->route('admin.productions.index')->with('status', 'Draf produksi dihapus.');
    }

    public function post(Request $request, Production $production, ProductionService $service): RedirectResponse
    {
        $leftovers = $request->validate([
            'leftover' => ['nullable', 'array'],
            'leftover.*' => ['in:stok,sampah'],
        ])['leftover'] ?? [];

        try {
            $service->post($production, $leftovers);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $production->refresh()->load('formula');
        $message = match (true) {
            $production->isVendor() => 'Bahan dikirim ke vendor '.$production->vendor.'. Catat penerimaan saat hasilnya kembali.',
            $production->formula->isSemiFinished() => 'Produksi dibukukan: hasilnya masuk Gudang Setengah Jadi.',
            default => 'Produksi dibukukan: bahan terpotong dari gudang dan knalpot jadi masuk Finish Good.',
        };

        return redirect()->route('admin.productions.show', $production)->with('status', $message);
    }

    public function receive(Request $request, Production $production, ProductionService $service): RedirectResponse
    {
        $data = $request->validate([
            'received_at' => ['required', 'date'],
            'qty' => ['required', 'integer', 'min:1'],
            'service_cost_per_unit' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $service->receive($production, (float) $data['qty'], $data['received_at'], (float) $data['service_cost_per_unit'], $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Hasil dari vendor diterima di Gudang Setengah Jadi; tagihan jasanya tercatat di Pembelian Bahan.');
    }

    public function destroyReceipt(ProductionReceipt $receipt, ProductionService $service): RedirectResponse
    {
        try {
            $service->deleteReceipt($receipt);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Penerimaan dihapus.');
    }

    public function cancel(Production $production, ProductionService $service): RedirectResponse
    {
        try {
            $service->cancel($production);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.productions.show', $production)->with('status', 'Pembukuan dibatalkan; stok dikembalikan dan nota kembali menjadi draf.');
    }

    private function form(Production $production): View
    {
        return view('admin.productions.form', [
            'production' => $production,
            'formulas' => Formula::query()->active()->orderBy('name')->get(['id', 'name', 'output_type', 'work_mode', 'vendor', 'process', 'service_cost']),
            'vendors' => Production::query()->whereNotNull('vendor')->distinct()->orderBy('vendor')->pluck('vendor')
                ->merge(Supplier::query()->active()->orderBy('name')->pluck('name'))->unique()->values(),
            'workers' => Production::query()->whereNotNull('worker')->distinct()->orderBy('worker')->pluck('worker'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $formula = Formula::find($request->input('formula_id'));
        $vendor = $request->input('mode') === Production::VENDOR;

        $data = $request->validate([
            'produced_at' => ['required', 'date'],
            'formula_id' => ['required', 'exists:formulas,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'mode' => ['nullable', Rule::in([Production::INTERNAL, Production::VENDOR])],
            'vendor' => [$vendor ? 'required' : 'nullable', 'string', 'max:255'],
            'process' => ['nullable', 'string', 'max:100'],
            'service_cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'worker' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ], [
            'qty.integer' => 'Jumlah produksi harus bilangan bulat.',
            'vendor.required' => 'Isi nama vendor yang mengerjakan.',
        ]);

        if ($vendor && ! $formula?->isSemiFinished()) {
            throw ValidationException::withMessages(['mode' => 'Proses vendor hanya untuk formula barang setengah jadi (mis. leheran bending).']);
        }

        if (! ($formula?->isSemiFinished() ? $formula->output_material_id : $formula?->item_id)) {
            throw ValidationException::withMessages(['formula_id' => 'Formula ini belum punya hasil (produk jadi / barang setengah jadi). Simpan ulang formulanya.']);
        }

        $data['mode'] = $vendor ? Production::VENDOR : Production::INTERNAL;
        $data['service_cost_per_unit'] = $vendor ? (float) ($data['service_cost_per_unit'] ?? 0) : 0;

        if (! $vendor) {
            $data['vendor'] = null;
            $data['process'] = null;
        }

        return $data;
    }
}
