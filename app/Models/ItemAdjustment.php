<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stok opname / penyesuaian stok knalpot jadi: stok sistem diganti hasil hitung fisik. */
class ItemAdjustment extends Model
{
    use RecordsActivity;

    public function activityLabel(): string
    {
        return 'Penyesuaian produk '.$this->adjustment_number.' · '.($this->item?->name ?? '');
    }

    public const REASONS = [
        'opname' => 'Hitung fisik (opname)',
        'rusak' => 'Rusak / cacat',
        'hilang' => 'Hilang',
        'sampel' => 'Sampel / promosi',
    ];

    protected $fillable = ['adjustment_number', 'adjusted_at', 'item_id', 'system_qty', 'actual_qty', 'difference', 'unit_cost', 'reason', 'counted_by', 'notes'];

    protected function casts(): array
    {
        return [
            'adjusted_at' => 'date',
            'system_qty' => 'decimal:2',
            'actual_qty' => 'decimal:2',
            'difference' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    /** Nilai selisih (minus = kerugian persediaan). */
    public function value(): float
    {
        return (float) $this->difference * (float) $this->unit_cost;
    }
}
