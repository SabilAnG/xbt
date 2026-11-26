<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Formula;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Services\DocumentNumber;
use App\Services\ProductionPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Rencana Produksi: pilih formula & jumlahnya, lihat kebutuhan bahan dan
 * kekurangannya, buat pembelian dari kekurangan, lalu mulai produksi.
 */
class ProductionPlanController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.plans.index', [
            'plans' => ProductionPlan::query()->with('lines.formula')
                ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('planned_for')->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function show(ProductionPlan $plan, ProductionPlanner $planner): View
    {
        $plan->load('lines.formula.item', 'lines.productions', 'purchases');

        return view('admin.plans.show', ['plan' => $plan, 'needs' => $planner->forPlan($plan)]);
    }

    public function create(Request $request): View
    {
        $plan = new ProductionPlan(['planned_for' => now()]);
        $lines = $request->filled('formula') ? [['formula_id' => (int) $request->input('formula'), 'qty' => 10]] : [];

        return $this->form($plan, $lines);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = DB::transaction(function () use ($request) {
            [$data, $lines] = $this->validated($request);
            $plan = ProductionPlan::create($data + [
                'plan_number' => DocumentNumber::next('production_plans', Carbon::parse($data['planned_for'])),
                'status' => 'draft',
            ]);
            $plan->lines()->createMany($lines);

            return $plan;
        });

        return redirect()->route('admin.plans.show', $plan)->with('status', 'Rencana produksi disimpan.');
    }

    public function edit(ProductionPlan $plan): View
    {
        return $this->form($plan, $plan->lines->map(fn ($line) => ['formula_id' => $line->formula_id, 'qty' => (float) $line->qty])->all());
    }

    public function update(Request $request, ProductionPlan $plan): RedirectResponse
    {
        if ($plan->productions()->exists()) {
            return back()->with('error', 'Rencana sudah punya nota produksi, jadi baris formulanya tidak bisa diubah lagi.');
        }

        DB::transaction(function () use ($request, $plan) {
            [$data, $lines] = $this->validated($request);
            $plan->update($data);
            $plan->lines()->delete();
            $plan->lines()->createMany($lines);
        });

        return redirect()->route('admin.plans.show', $plan)->with('status', 'Rencana produksi disimpan.');
    }

    public function destroy(ProductionPlan $plan): RedirectResponse
    {
        if ($plan->productions()->exists()) {
            return back()->with('error', 'Rencana sudah punya nota produksi. Hapus atau batalkan produksinya dulu.');
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('status', 'Rencana produksi dihapus.');
    }

    /** Pecah kekurangan barang yang bisa dibuat & dibeli: sebagian dibeli, sisanya dibuat. */
    public function updateSource(Request $request, ProductionPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'material_id' => ['required', 'exists:materials,id'],
            'buy_qty' => ['required', 'numeric', 'min:0'],
        ], ['buy_qty.required' => 'Isi jumlah yang dibeli (0 bila semua dibuat).']);

        $plan->sources()->updateOrCreate(['material_id' => $data['material_id']], ['buy_qty' => (float) $data['buy_qty']]);

        return back()->with('status', 'Pembagian buat/beli disimpan.');
    }

    /** Tambahkan formula setengah jadi ke rencana untuk bagian yang dibuat sendiri. */
    public function addFormula(Request $request, ProductionPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'formula_id' => ['required', 'exists:formulas,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $line = $plan->lines()->where('formula_id', $data['formula_id'])->whereDoesntHave('productions')->first();

        if ($line) {
            $line->increment('qty', (float) $data['qty']);
        } else {
            $plan->lines()->create(['formula_id' => $data['formula_id'], 'qty' => (float) $data['qty'], 'sort_order' => (int) $plan->lines()->max('sort_order') + 1]);
        }

        return back()->with('status', 'Formula ditambahkan ke rencana.');
    }

    /** Buat nota produksi (draf) untuk setiap baris yang belum punya. */
    public function startProductions(ProductionPlan $plan): RedirectResponse
    {
        try {
            $created = $this->createProductions($plan);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.productions.index', ['rencana' => $plan->id])
            ->with('status', $created ? "{$created} nota produksi dibuat. Periksa rencana potongnya lalu bukukan satu per satu." : 'Semua baris rencana sudah punya nota produksi.');
    }

    private function createProductions(ProductionPlan $plan): int
    {
        return DB::transaction(function () use ($plan) {
            $created = 0;

            foreach ($plan->lines()->with('formula', 'productions')->get() as $line) {
                if ($line->productions->isNotEmpty()) {
                    continue;
                }

                if (! ($line->formula->isSemiFinished() ? $line->formula->output_material_id : $line->formula->item_id)) {
                    throw new RuntimeException("Formula {$line->formula->name} belum punya produk jadi.");
                }

                Production::create([
                    'production_number' => DocumentNumber::next('productions', now()),
                    'produced_at' => now()->toDateString(),
                    'formula_id' => $line->formula_id,
                    'item_id' => $line->formula->isSemiFinished() ? null : $line->formula->item_id,
                    'production_plan_line_id' => $line->id,
                    'qty' => $line->qty,
                    'status' => Production::DRAFT,
                    'mode' => $line->formula->isVendorWork() ? Production::VENDOR : Production::INTERNAL,
                    'vendor' => $line->formula->isVendorWork() ? $line->formula->vendor : null,
                    'process' => $line->formula->isVendorWork() ? $line->formula->process : null,
                    'service_cost_per_unit' => $line->formula->serviceCostPerUnit(),
                ]);
                $created++;
            }

            $plan->refreshStatus();

            return $created;
        });
    }

    /**
     * @param  list<array{formula_id: int, qty: float}>  $lines
     */
    private function form(ProductionPlan $plan, array $lines): View
    {
        return view('admin.plans.form', [
            'plan' => $plan,
            'lines' => array_values(old('lines', $lines ?: [['formula_id' => '', 'qty' => '']])),
            'formulas' => Formula::query()->active()->orderBy('name')->get()->mapWithKeys(fn (Formula $formula) => [
                $formula->id => $formula->name.' — HPP ± '.rupiah($formula->estimatedHpp()),
            ]),
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array{formula_id: int, qty: float, sort_order: int}>}
     */
    private function validated(Request $request): array
    {
        $request->merge(['lines' => collect($request->input('lines', []))->filter(fn ($line) => filled($line['formula_id'] ?? null))->values()->all()]);

        $data = $request->validate([
            'planned_for' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.formula_id' => ['required', Rule::exists('formulas', 'id'), 'distinct'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
        ], [
            'lines.required' => 'Pilih minimal satu formula.',
            'lines.*.formula_id.distinct' => 'Formula yang sama dipilih dua kali.',
            'lines.*.qty.required' => 'Isi jumlah yang akan dibuat.',
            'lines.*.qty.gt' => 'Jumlah harus lebih dari 0.',
        ]);

        $lines = collect($data['lines'])->values()->map(fn (array $line, int $i) => [
            'formula_id' => (int) $line['formula_id'], 'qty' => (float) $line['qty'], 'sort_order' => $i,
        ])->all();
        unset($data['lines']);

        return [$data, $lines];
    }
}
