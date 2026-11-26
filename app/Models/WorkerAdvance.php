<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kasbon pekerja; uang keluar dari dompet, dipotong saat penggajian. */
class WorkerAdvance extends Model
{
    protected $fillable = ['worker_id', 'given_at', 'amount', 'wallet_id', 'notes'];

    protected function casts(): array
    {
        return ['given_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
