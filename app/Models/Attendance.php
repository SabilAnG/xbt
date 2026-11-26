<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Absensi harian satu pekerja, termasuk jam lembur. */
class Attendance extends Model
{
    /** status => [label, hari kerja dihitung, dapat uang makan] */
    public const STATUSES = [
        'hadir' => ['Hadir', 1.0, true],
        'setengah' => ['½ hari', 0.5, true],
        'izin' => ['Izin', 0.0, false],
        'sakit' => ['Sakit', 0.0, false],
        'alpa' => ['Alpa', 0.0, false],
        'libur' => ['Libur', 0.0, false],
    ];

    protected $fillable = ['worker_id', 'worked_on', 'status', 'overtime_hours', 'notes', 'worker_payroll_id'];

    protected function casts(): array
    {
        return ['worked_on' => 'date', 'overtime_hours' => 'decimal:2'];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(WorkerPayroll::class, 'worker_payroll_id');
    }

    public function label(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function isLocked(): bool
    {
        return $this->worker_payroll_id !== null;
    }
}
