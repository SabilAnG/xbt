<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Foto stok fisik saat opname: bukti kondisi & jumlah barang di gudang. */
class StockPhoto extends Model
{
    protected $fillable = ['subject_type', 'subject_id', 'warehouse_id', 'path', 'taken_at', 'counted_by', 'counted_qty'];

    protected function casts(): array
    {
        return ['taken_at' => 'date', 'counted_qty' => 'decimal:2'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function url(): string
    {
        return asset($this->path);
    }
}
