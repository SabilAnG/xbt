<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Slip gaji mingguan satu pekerja. */
class WorkerPayroll extends Model
{
    public const DRAFT = 'draft';

    public const PAID = 'paid';

    protected $fillable = [
        'payroll_number', 'worker_id', 'period_start', 'period_end', 'days_full', 'days_half', 'overtime_hours',
        'daily_wage', 'meal_allowance', 'overtime_rate', 'wage_total', 'meal_total', 'overtime_total', 'gross_pay',
        'advance_deduction', 'net_pay', 'status', 'paid_at', 'wallet_id', 'expense_id', 'deduction_expense_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'date',
            'days_full' => 'decimal:2',
            'days_half' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'daily_wage' => 'decimal:2',
            'meal_allowance' => 'decimal:2',
            'overtime_rate' => 'decimal:2',
            'wage_total' => 'decimal:2',
            'meal_total' => 'decimal:2',
            'overtime_total' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'advance_deduction' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class)->orderBy('worked_on');
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    public function statusLabel(): string
    {
        return $this->isPaid() ? 'LUNAS' : 'BELUM LUNAS';
    }

    public function periodLabel(): string
    {
        return $this->period_start->translatedFormat('d M').' – '.$this->period_end->translatedFormat('d M Y');
    }
}
