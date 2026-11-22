<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tukang (data tukang di sheet Daftar). */
class Worker extends Model
{
    protected $fillable = ['name', 'daily_wage', 'account', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['daily_wage' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(WorkerPayroll::class);
    }
}
