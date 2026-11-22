<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu baris di sheet "Absen Tukang": tanggal + catatan + nilai per tukang. */
class AttendanceDay extends Model
{
    protected $fillable = ['date', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function valueFor(Worker $worker): ?float
    {
        $row = $this->attendances->firstWhere('worker_id', $worker->id);

        return $row ? (float) $row->value : null;
    }
}
