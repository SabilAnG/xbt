<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pekerja bengkel dengan upah harian, uang makan, dan tarif lembur. */
class Worker extends Model
{
    protected $fillable = ['name', 'phone', 'position', 'daily_wage', 'meal_allowance', 'overtime_rate', 'joined_at', 'is_active', 'notes'];

    protected function casts(): array
    {
        return [
            'daily_wage' => 'decimal:2',
            'meal_allowance' => 'decimal:2',
            'overtime_rate' => 'decimal:2',
            'joined_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class)->orderByDesc('worked_on');
    }

    public function advances(): HasMany
    {
        return $this->hasMany(WorkerAdvance::class)->orderByDesc('given_at')->orderByDesc('id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(WorkerPayroll::class)->orderByDesc('period_start')->orderByDesc('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Sisa kasbon = kasbon diberikan − yang sudah dipotong di slip gaji. */
    public function advanceBalance(?int $exceptPayrollId = null): float
    {
        $given = (float) $this->advances()->sum('amount');
        $deducted = (float) $this->payrolls()->when($exceptPayrollId, fn ($query) => $query->whereKeyNot($exceptPayrollId))->sum('advance_deduction');

        return max(round($given - $deducted, 2), 0);
    }
}
