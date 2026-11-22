<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekap gaji tukang per minggu (sheet "Gaji Tukang"):
 * HARUS DIBAYAR = hari masuk × upah/hari + tambahan − potongan.
 */
class WorkerPayroll extends Model
{
    public const STATUSES = ['Belum', 'Sudah'];

    protected $fillable = ['period_start', 'period_end', 'worker_id', 'bonus', 'deduction', 'status', 'paid_at', 'notes'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'date',
            'bonus' => 'decimal:2',
            'deduction' => 'decimal:2',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** Hari masuk dari Absen Tukang dalam periode (1 / 0,5 per hari). */
    public function daysPresent(): float
    {
        return (float) Attendance::query()
            ->where('worker_id', $this->worker_id)
            ->whereHas('day', fn ($query) => $query
                ->whereDate('date', '>=', $this->period_start->toDateString())
                ->whereDate('date', '<=', $this->period_end->toDateString()))
            ->sum('value');
    }

    public function dailyWage(): float
    {
        return (float) $this->worker?->daily_wage;
    }

    public function amountDue(): float
    {
        return $this->daysPresent() * $this->dailyWage() + (float) $this->bonus - (float) $this->deduction;
    }
}
