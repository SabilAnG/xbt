<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Sheet "Absen Tukang" + data tukang di sheet "Daftar". */
class WorkerController extends Controller
{
    public function attendance(Request $request): View
    {
        $days = AttendanceDay::query()->with('attendances')
            ->when($request->input('month'), fn ($query, $month) => $query->where('date', 'like', "{$month}%"))
            ->latest('date')
            ->paginate(31)->withQueryString();

        return view('admin.workers.attendance', ['days' => $days, 'workers' => Worker::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function createDay(): View
    {
        return view('admin.workers.day-form', [
            'day' => new AttendanceDay(['date' => now()]),
            'workers' => Worker::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function storeDay(Request $request): RedirectResponse
    {
        $data = $this->validatedDay($request);

        DB::transaction(function () use ($data) {
            $day = AttendanceDay::create(['date' => $data['date'], 'notes' => $data['notes'] ?? null]);
            $this->syncValues($day, $data['values'] ?? []);
        });

        return redirect()->route('admin.attendance.index')->with('status', 'Absen disimpan.');
    }

    public function editDay(AttendanceDay $day): View
    {
        return view('admin.workers.day-form', ['day' => $day->load('attendances'), 'workers' => Worker::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function updateDay(Request $request, AttendanceDay $day): RedirectResponse
    {
        $data = $this->validatedDay($request, $day);

        DB::transaction(function () use ($day, $data) {
            $day->update(['date' => $data['date'], 'notes' => $data['notes'] ?? null]);
            $this->syncValues($day, $data['values'] ?? []);
        });

        return redirect()->route('admin.attendance.index')->with('status', 'Absen diperbarui.');
    }

    public function destroyDay(AttendanceDay $day): RedirectResponse
    {
        $day->delete();

        return back()->with('status', 'Baris absen dihapus.');
    }

    /** Data tukang tampil satu halaman dengan data host. */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.hosts.index');
    }

    public function store(Request $request): RedirectResponse
    {
        Worker::create([...$this->validatedWorker($request), 'sort_order' => (int) Worker::max('sort_order') + 1]);

        return back()->with('status', 'Tukang ditambahkan.');
    }

    public function update(Request $request, Worker $worker): RedirectResponse
    {
        $worker->update($this->validatedWorker($request, $worker));

        return back()->with('status', "Data tukang {$worker->name} disimpan.");
    }

    public function destroy(Worker $worker): RedirectResponse
    {
        if ($worker->attendances()->exists() || $worker->payrolls()->exists()) {
            return back()->with('error', 'Tukang sudah punya absen atau rekap gaji. Nonaktifkan saja.');
        }

        $worker->delete();

        return back()->with('status', 'Tukang dihapus.');
    }

    /**
     * @return array{date: string, notes?: ?string, values?: array<int, ?string>}
     */
    private function validatedDay(Request $request, ?AttendanceDay $day = null): array
    {
        return $request->validate([
            'date' => ['required', 'date', Rule::unique('attendance_days', 'date')->ignore($day)],
            'notes' => ['nullable', 'string', 'max:255'],
            'values' => ['array'],
            'values.*' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ], ['date.unique' => 'Tanggal ini sudah punya baris absen; ubah baris itu saja.']);
    }

    /**
     * @param  array<int, ?string>  $values  worker_id => 1 / 0,5 / kosong
     */
    private function syncValues(AttendanceDay $day, array $values): void
    {
        $day->attendances()->delete();

        foreach ($values as $workerId => $value) {
            if ($value !== null && $value !== '' && (float) $value > 0) {
                $day->attendances()->create(['worker_id' => $workerId, 'value' => (float) $value]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedWorker(Request $request, ?Worker $worker = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('workers', 'name')->ignore($worker)],
            'daily_wage' => ['required', 'numeric', 'min:0'],
            'account' => ['nullable', 'string', 'max:255'],
        ]);

        return [...$data, 'is_active' => $request->boolean('is_active', true)];
    }
}
