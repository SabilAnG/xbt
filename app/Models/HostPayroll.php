<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekap gaji host per periode (sheet "Gaji Host"). Total jam diambil dari Jam
 * Live dalam periode, tarif dari data host — sama seperti rumus di Excel.
 */
class HostPayroll extends Model
{
    public const STATUSES = ['Belum', 'Sudah'];

    protected $fillable = ['period_start', 'period_end', 'host_id', 'status', 'paid_at', 'notes'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'paid_at' => 'date'];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function totalHours(): float
    {
        return (float) LiveSession::query()
            ->where('host_id', $this->host_id)
            ->whereDate('date', '>=', $this->period_start->toDateString())
            ->whereDate('date', '<=', $this->period_end->toDateString())
            ->get()
            ->sum(fn (LiveSession $session) => $session->totalHours());
    }

    public function rate(): float
    {
        return (float) $this->host?->hourly_rate;
    }

    public function amountDue(): float
    {
        return $this->totalHours() * $this->rate();
    }
}
