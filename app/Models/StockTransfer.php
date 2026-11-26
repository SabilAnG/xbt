<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Pindah barang antar gudang. Total stok dan nilainya tetap; hanya lokasinya berubah. */
class StockTransfer extends Model
{
    use RecordsActivity;

    public function activityLabel(): string
    {
        return 'Pindah barang '.$this->transfer_number;
    }

    protected $fillable = ['transfer_number', 'transferred_at', 'from_warehouse_id', 'to_warehouse_id', 'moved_by', 'notes'];

    protected function casts(): array
    {
        return ['transferred_at' => 'date'];
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(MaterialMovement::class, 'source');
    }
}
