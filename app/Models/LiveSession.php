<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu sesi live (sheet "Jam Live"). */
class LiveSession extends Model
{
    protected $fillable = ['date', 'host_id', 'start_time', 'end_time', 'manual_hours', 'store_account', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date', 'manual_hours' => 'decimal:2'];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    /**
     * TOTAL JAM: jam manual bila diisi, selain itu selisih jam selesai − mulai
     * (lewat tengah malam tetap benar), dibulatkan 2 desimal.
     */
    public function totalHours(): float
    {
        if ($this->manual_hours !== null) {
            return (float) $this->manual_hours;
        }

        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        $minutes = $this->minutesOf($this->end_time) - $this->minutesOf($this->start_time);

        if ($minutes < 0) {
            $minutes += 24 * 60;
        }

        return round($minutes / 60, 2);
    }

    private function minutesOf(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));

        return $parts[0] * 60 + ($parts[1] ?? 0);
    }
}
