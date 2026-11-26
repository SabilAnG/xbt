<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Worker;
use App\Models\WorkerAdvance;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Daftar pekerja, kasbon, dan riwayatnya. */
class WorkerController extends Controller
{
    public function index(Request $request): View
    {
        $today = Attendance::query()->whereDate('worked_on', today())->pluck('status', 'worker_id');
        $workers = Worker::query()
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('position', 'like', "%{$search}%")
            ))
            ->when($request->input('status') !== 'semua', fn ($query) => $query->where('is_active', true))
            ->orderByDesc('is_active')->orderBy('name')->get();

        return view('admin.workers.index', [
            'workers' => $workers,
            'today' => $today,
            'balances' => $workers->mapWithKeys(fn (Worker $worker) => [$worker->id => $worker->advanceBalance()]),
        ]);
    }

    public function create(): View
    {
        return view('admin.workers.form', ['worker' => new Worker(['is_active' => true, 'joined_at' => today()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $worker = Worker::create($this->validated($request));

        return redirect()->route('admin.workers.show', $worker)->with('status', "Pekerja {$worker->name} ditambahkan.");
    }

    public function show(Worker $worker): View
    {
        return view('admin.workers.show', [
            'worker' => $worker,
            'attendances' => $worker->attendances()->limit(31)->get(),
            'advances' => $worker->advances()->with('wallet')->get(),
            'payrolls' => $worker->payrolls()->limit(12)->get(),
            'balance' => $worker->advanceBalance(),
            'month' => $worker->attendances()->whereBetween('worked_on', [today()->startOfMonth(), today()->endOfMonth()])->get()->countBy('status'),
        ]);
    }

    public function edit(Worker $worker): View
    {
        return view('admin.workers.form', ['worker' => $worker]);
    }

    public function update(Request $request, Worker $worker): RedirectResponse
    {
        $worker->update($this->validated($request));

        return redirect()->route('admin.workers.show', $worker)->with('status', 'Data pekerja disimpan.');
    }

    public function destroy(Worker $worker): RedirectResponse
    {
        if ($worker->payrolls()->exists() || $worker->advances()->exists()) {
            return back()->with('error', "{$worker->name} sudah punya slip gaji/kasbon. Nonaktifkan saja bila sudah tidak bekerja.");
        }

        $worker->delete();

        return redirect()->route('admin.workers.index')->with('status', 'Pekerja dihapus.');
    }

    public function storeAdvance(Request $request, Worker $worker, PayrollService $service): RedirectResponse
    {
        $data = $request->validate([
            'given_at' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'wallet_id' => ['required', 'exists:wallets,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['wallet_id.required' => 'Pilih dompet asal uang kasbon.']);

        try {
            $service->giveAdvance($worker, (float) $data['amount'], (int) $data['wallet_id'], $data['given_at'], $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Kasbon '.rupiah($data['amount']).' dicatat.');
    }

    public function destroyAdvance(WorkerAdvance $advance, PayrollService $service): RedirectResponse
    {
        try {
            $service->deleteAdvance($advance);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Kasbon dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:100'],
            'daily_wage' => ['required', 'numeric', 'min:0'],
            'meal_allowance' => ['nullable', 'numeric', 'min:0'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0'],
            'joined_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ], ['daily_wage.required' => 'Isi upah harian.']);

        $data['meal_allowance'] = (float) ($data['meal_allowance'] ?? 0);
        $data['overtime_rate'] = (float) ($data['overtime_rate'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
