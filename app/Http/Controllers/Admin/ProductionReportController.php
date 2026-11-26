<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialPurchase;
use App\Models\Production;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Laporan produksi per periode: unit, biaya, HPP rata-rata per formula, per pekerja, dan jasa vendor. */
class ProductionReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $from = Carbon::parse($request->input('dari', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('sampai', now()->endOfMonth()->toDateString()))->endOfDay();
        $range = [$from->toDateString(), $to->toDateString()];

        $productions = Production::query()->posted()->with('formula')->whereBetween('produced_at', $range)->get();
        $services = MaterialPurchase::query()->where('kind', 'jasa')->whereBetween('purchased_at', $range)->get();

        return view('admin.reports.production', [
            'from' => $from,
            'to' => $to,
            'summary' => [
                'count' => $productions->count(),
                'units' => (float) $productions->sum('qty'),
                'material' => (float) $productions->sum('material_cost'),
                'other' => (float) $productions->sum('other_cost'),
                'service' => (float) $productions->sum('service_cost'),
                'waste' => (float) $productions->sum('waste_cost'),
                'total' => (float) $productions->sum('total_cost'),
            ],
            'byFormula' => $this->group($productions, fn (Production $job) => $job->formula?->name ?? 'Tanpa formula'),
            'byWorker' => $this->group($productions, fn (Production $job) => $job->worker ?: 'Belum diisi'),
            'byVendor' => $services->groupBy(fn (MaterialPurchase $bill) => $bill->supplier ?: 'Tanpa nama')
                ->map(fn (Collection $bills, string $vendor) => [
                    'vendor' => $vendor,
                    'bills' => $bills->count(),
                    'total' => (float) $bills->sum('total'),
                    'payable' => (float) $bills->sum(fn (MaterialPurchase $bill) => $bill->payable()),
                ])->sortByDesc('total')->values(),
        ]);
    }

    /**
     * @param  Collection<int, Production>  $productions
     * @return Collection<int, array{name: string, count: int, units: float, total: float, waste: float, hpp: float}>
     */
    private function group(Collection $productions, callable $key): Collection
    {
        return $productions->groupBy($key)->map(function (Collection $jobs, string $name) {
            $units = (float) $jobs->sum('qty');
            $total = (float) $jobs->sum('total_cost');

            return [
                'name' => $name,
                'count' => $jobs->count(),
                'units' => $units,
                'total' => $total,
                'waste' => (float) $jobs->sum('waste_cost'),
                'hpp' => $units > 0 ? $total / $units : 0,
            ];
        })->sortByDesc('units')->values();
    }
}
