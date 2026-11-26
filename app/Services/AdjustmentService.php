<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialAdjustment;
use App\Models\MaterialMovement;
use App\Models\PipePiece;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stok awal, koreksi, dan barang rusak.
 *
 * Pipa masuk dicatat sebagai sejumlah potongan dengan panjang tertentu (batang
 * utuh ke Bahan Baku, potongan ke Stok Sisa). Pipa keluar = satu potongan
 * diafkir.
 */
class AdjustmentService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array{adjusted_at: string, material_id: int, warehouse_id: int, direction: string, reason: string, qty?: ?float, length_cm?: ?float, unit_cost?: ?float, pipe_piece_id?: ?int, notes?: ?string}  $data
     */
    public function create(array $data): MaterialAdjustment
    {
        return DB::transaction(function () use ($data) {
            $material = Material::findOrFail($data['material_id']);
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);
            $date = Carbon::parse($data['adjusted_at']);
            $piece = null;

            if ($material->isPipe() && $data['direction'] === 'out') {
                $piece = PipePiece::where('material_id', $material->id)->where('status', PipePiece::AVAILABLE)->findOrFail($data['pipe_piece_id']);
                $warehouse = $piece->warehouse;
            }

            $unitCost = (float) (($data['unit_cost'] ?? null) ?: $material->unitCost());

            $adjustment = MaterialAdjustment::create([
                'adjustment_number' => DocumentNumber::next('material_adjustments', $date),
                'adjusted_at' => $date->toDateString(),
                'material_id' => $material->id,
                'warehouse_id' => $warehouse->id,
                'pipe_piece_id' => $piece?->id,
                'direction' => $data['direction'],
                'qty' => $piece ? (float) $piece->length_cm : (float) ($data['qty'] ?? 0),
                'length_cm' => $material->isPipe() ? ($piece ? (float) $piece->length_cm : (float) ($data['length_cm'] ?? 0)) : null,
                'unit_cost' => $piece ? (float) $piece->cost_per_cm : $unitCost,
                'reason' => $data['reason'],
                'counted_by' => $data['counted_by'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $note = $adjustment->adjustment_number.' · '.$adjustment->reasonLabel().($adjustment->notes ? ' · '.$adjustment->notes : '');

            if ($piece) {
                $this->stock->recordPiece($piece, $warehouse, 'afkir', 0, (float) $piece->length_cm, $adjustment, $date, $note);
                $piece->forceFill(['status' => PipePiece::SCRAP])->save();
                $this->stock->recalculate($material);
            } elseif ($material->isPipe()) {
                $this->stock->receivePieces($material, $warehouse, (int) $adjustment->qty, (float) $adjustment->length_cm, $unitCost, 'penyesuaian', $adjustment, $date, $note);
            } elseif ($data['direction'] === 'in') {
                $this->stock->receiveUnits($material, $warehouse, (float) $adjustment->qty, $unitCost, 'penyesuaian', $adjustment, $date, $note);
            } else {
                $this->stock->issueUnits($material, $warehouse, (float) $adjustment->qty, 'penyesuaian', $adjustment, $date, $note);
            }

            return $adjustment;
        });
    }

    public function delete(MaterialAdjustment $adjustment): void
    {
        DB::transaction(function () use ($adjustment) {
            $material = $adjustment->material()->with('stocks')->firstOrFail();
            $lastOwn = (int) $adjustment->movements()->max('id');

            if ($adjustment->pipe_piece_id) {
                $piece = $adjustment->piece;

                if (! $piece || $piece->status !== PipePiece::SCRAP || MaterialMovement::where('pipe_piece_id', $piece->id)->where('id', '>', $lastOwn)->exists()) {
                    throw new RuntimeException('Potongan ini sudah berubah lagi, jadi penyesuaiannya tidak bisa dihapus.');
                }

                $piece->forceFill(['status' => PipePiece::AVAILABLE])->save();
            } elseif ($material->isPipe()) {
                $pieces = PipePiece::where('source_type', $adjustment->getMorphClass())->where('source_id', $adjustment->id)->get();

                if ($pieces->contains(fn (PipePiece $piece) => ! $piece->isUntouched())) {
                    throw new RuntimeException('Pipa dari penyesuaian ini sudah dipotong, jadi tidak bisa dihapus.');
                }

                PipePiece::whereIn('id', $pieces->pluck('id'))->delete();
            } elseif ($adjustment->direction === 'in' && $material->stockIn($adjustment->warehouse_id) + 0.0001 < (float) $adjustment->qty) {
                throw new RuntimeException('Stok dari penyesuaian ini sudah terpakai, jadi tidak bisa dihapus.');
            }

            $this->stock->forget($adjustment);
            $adjustment->delete();
        });
    }
}
