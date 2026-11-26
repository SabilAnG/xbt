<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;

/** Penggajian mingguan pekerja. */
class PayrollController extends Controller
{
    public function index(Request $request, PayrollService $service): View
    {
        $start = Carbon::parse($request->input('minggu', today()->toDateString()))->startOfWeek();
        $end = $start->copy()->addDays(6);
        $workers = Worker::query()->active()->orderBy('name')->get();

        return view('admin.payrolls.index', [
            'start' => $start,
            'end' => $end,
            'summaries' => $workers->map(fn (Worker $worker) => $service->summary($worker, $start->toDateString(), $end->toDateString())),
            'slips' => WorkerPayroll::query()->with('worker')
                ->where(fn ($query) => $query->whereBetween('period_start', [$start, $end])->orWhere('status', WorkerPayroll::DRAFT))
                ->latest('period_start')->latest('id')->get(),
            'unpaid' => (float) WorkerPayroll::query()->where('status', WorkerPayroll::DRAFT)->sum('net_pay'),
        ]);
    }

    public function store(Request $request, PayrollService $service): RedirectResponse
    {
        $data = $request->validate([
            'worker_id' => ['required', 'exists:workers,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'advance_deduction' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $payroll = $service->create(Worker::findOrFail($data['worker_id']), $data['period_start'], $data['period_end'],
                isset($data['advance_deduction']) ? (float) $data['advance_deduction'] : null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payrolls.show', $payroll)->with('status', 'Slip gaji dibuat.');
    }

    /** Buat slip untuk semua pekerja yang punya absensi belum digaji minggu ini. */
    public function storeAll(Request $request, PayrollService $service): RedirectResponse
    {
        $data = $request->validate(['period_start' => ['required', 'date'], 'period_end' => ['required', 'date']]);
        $made = 0;

        foreach (Worker::query()->active()->get() as $worker) {
            if ($service->summary($worker, $data['period_start'], $data['period_end'])['attendances']->isNotEmpty()) {
                $service->create($worker, $data['period_start'], $data['period_end']);
                $made++;
            }
        }

        return back()->with($made ? 'status' : 'error', $made ? "{$made} slip gaji dibuat." : 'Tidak ada absensi yang belum digaji pada minggu ini.');
    }

    public function show(WorkerPayroll $payroll): View
    {
        return view('admin.payrolls.show', ['payroll' => $payroll->load('worker', 'attendances', 'wallet')]);
    }

    public function pay(Request $request, WorkerPayroll $payroll, PayrollService $service): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date'],
            'wallet_id' => ['required', 'exists:wallets,id'],
        ], ['wallet_id.required' => 'Pilih dompet yang dipakai membayar.']);

        try {
            $service->pay($payroll, (int) $data['wallet_id'], $data['paid_at']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Gaji '.$payroll->worker->name.' '.rupiah($payroll->net_pay).' dibayar dan tercatat di Pengeluaran.');
    }

    public function unpay(WorkerPayroll $payroll, PayrollService $service): RedirectResponse
    {
        $service->unpay($payroll);

        return back()->with('status', 'Pembayaran gaji dibatalkan; pengeluarannya dihapus.');
    }

    public function destroy(WorkerPayroll $payroll, PayrollService $service): RedirectResponse
    {
        try {
            $service->delete($payroll);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payrolls.index', ['minggu' => $payroll->period_start->toDateString()])->with('status', 'Slip gaji dihapus.');
    }
}
