<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Upah borongan dari ongkos formula, didapat tukang saat produksinya dibukukan; dibayar lewat slip gaji. */
class WorkerPieceEarning extends Model
{
    protected $fillable = ['worker_id', 'production_id', 'worker_payroll_id', 'earned_on', 'name', 'qty', 'rate', 'amount'];

    protected function casts(): array
    {
        return ['earned_on' => 'date', 'qty' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(WorkerPayroll::class, 'worker_payroll_id');
    }
}
