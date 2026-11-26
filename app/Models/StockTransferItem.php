<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris pindah barang; qty dalam satuan pakai (cm untuk pipa). */
class StockTransferItem extends Model
{
    protected $fillable = ['stock_transfer_id', 'material_id', 'pipe_piece_id', 'qty', 'value'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'value' => 'decimal:2',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(PipePiece::class, 'pipe_piece_id');
    }
}
