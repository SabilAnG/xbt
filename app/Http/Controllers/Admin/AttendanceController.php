<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Absensi harian semua pekerja + rekap mingguan. */
class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $date = Carbon::parse($request->input('tanggal', today()->toDateString()));
        $weekStart = $date->copy()->startOfWeek();
        $days = collect(range(0, 6))->map(fn (int $i) => $weekStart->copy()->addDays($i));
        $workers = Worker::query()->active()->orderBy('name')->get();
        $records = Attendance::query()->whereIn('worker_id', $workers->pluck('id'))
            ->whereBetween('worked_on', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
            ->get()->groupBy('worker_id');

        return view('admin.attendances.index', [
            'date' => $date,
            'days' => $days,
            'workers' => $workers,
            'records' => $records,
            'today' => $records->map(fn ($rows) => $rows->first(fn (Attendance $row) => $row->worked_on->isSameDay($date))),
        ]);
    }

    /** Simpan absensi satu tanggal untuk semua pekerja (baris yang sudah digaji tidak diubah). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'worked_on' => ['required', 'date'],
            'rows' => ['required', 'array'],
            'rows.*.status' => ['nullable', Rule::in(array_keys(Attendance::STATUSES))],
            'rows.*.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'rows.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $saved = 0;
        $locked = 0;

        foreach ($data['rows'] as $workerId => $row) {
            $existing = Attendance::query()->where('worker_id', $workerId)->whereDate('worked_on', $data['worked_on'])->first();

            if ($existing?->isLocked()) {
                $locked++;

                continue;
            }

            if (blank($row['status'] ?? null)) {
                $existing?->delete();

                continue;
            }

            Attendance::updateOrCreate(
                ['worker_id' => $workerId, 'worked_on' => $data['worked_on']],
                ['status' => $row['status'], 'overtime_hours' => (float) ($row['overtime_hours'] ?? 0), 'notes' => $row['notes'] ?? null],
            );
            $saved++;
        }

        $message = "Absensi {$saved} pekerja disimpan.".($locked ? " {$locked} pekerja tidak diubah karena sudah masuk slip gaji." : '');

        return redirect()->route('admin.attendances.index', ['tanggal' => $data['worked_on']])->with('status', $message);
    }
}
