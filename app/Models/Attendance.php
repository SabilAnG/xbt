<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = ['attendance_day_id', 'worker_id', 'value'];

    protected function casts(): array
    {
        return ['value' => 'decimal:1'];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(AttendanceDay::class, 'attendance_day_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
