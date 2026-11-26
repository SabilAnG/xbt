<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu potongan pipa dalam produksi. Keadaan potongan sebelum dipotong
 * disimpan supaya produksi bisa dibatalkan dengan tepat.
 */
class ProductionCut extends Model
{
    protected $fillable = [
        'production_id', 'pipe_piece_id', 'label', 'length_cm', 'kerf_cm',
        'before_warehouse_id', 'before_length_cm', 'before_status', 'cost', 'sequence',
    ];

    protected function casts(): array
    {
        return [
            'length_cm' => 'decimal:2',
            'kerf_cm' => 'decimal:2',
            'before_length_cm' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(PipePiece::class, 'pipe_piece_id');
    }
}
