<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\MaterialStock;
use App\Models\PipePiece;
use App\Models\Warehouse;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Satu-satunya tempat yang menulis kartu stok bahan.
 *
 * Bahan per satuan memakai harga rata-rata bergerak (moving average). Pipa
 * tidak dirata-rata per batang: setiap potongan membawa harga per cm dari
 * pembeliannya sendiri, dan avg_cost bahan = nilai potongan tersedia ÷ total
 * panjangnya.
 */
class StockService
{
    /** Barang per satuan masuk gudang. */
    public function receiveUnits(Material $material, Warehouse $warehouse, float $qty, float $unitCost, string $type, ?Model $source, CarbonInterface|string $date, ?string $notes = null): MaterialMovement
    {
        if ($qty <= 0) {
            throw new RuntimeException('Jumlah masuk harus lebih dari 0.');
        }

        $movement = $this->record($material, $warehouse, null, $type, $qty, 0, $unitCost, $source, $date, $notes);
        $this->recalculate($material);

        return $movement;
    }

    /**
     * Batang/potongan pipa masuk gudang, masing-masing jadi satu PipePiece.
     *
     * @return list<PipePiece>
     */
    public function receivePieces(Material $material, Warehouse $warehouse, int $count, float $lengthCm, float $costPerCm, string $type, ?Model $source, CarbonInterface|string $date, ?string $notes = null): array
    {
        if ($count <= 0 || $lengthCm <= 0) {
            throw new RuntimeException('Jumlah dan panjang pipa harus lebih dari 0.');
        }

        $pieces = [];

        for ($i = 0; $i < $count; $i++) {
            $piece = PipePiece::create([
                'code' => (string) Str::uuid(),
                'material_id' => $material->id,
                'warehouse_id' => $warehouse->id,
                'initial_length_cm' => $lengthCm,
                'length_cm' => $lengthCm,
                'cost_per_cm' => $costPerCm,
                'status' => PipePiece::AVAILABLE,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
            ]);
            $piece->forceFill(['code' => sprintf('PP-%05d', $piece->id)])->save();

            $this->record($material, $warehouse, $piece, $type, $lengthCm, 0, $costPerCm, $source, $date, $notes ?? $piece->code);
            $pieces[] = $piece;
        }

        $this->recalculate($material);

        return $pieces;
    }

    /**
     * Barang per satuan keluar dari gudang dengan harga rata-rata saat itu.
     *
     * @return float nilai yang keluar
     */
    public function issueUnits(Material $material, Warehouse $warehouse, float $qty, string $type, ?Model $source, CarbonInterface|string $date, ?string $notes = null): float
    {
        $material->load('stocks');
        $available = $material->stockIn($warehouse);

        if ($qty > $available + 0.0001) {
            throw new RuntimeException(sprintf('Stok %s di %s tidak cukup: ada %s, butuh %s %s.', $material->label(), $warehouse->name, qty($available), qty($qty), $material->stockUnit()));
        }

        $unitCost = (float) $material->avg_cost;
        $this->record($material, $warehouse, null, $type, 0, $qty, $unitCost, $source, $date, $notes);
        $this->recalculate($material);

        return $qty * $unitCost;
    }

    /**
     * Barang per satuan keluar dari gudang mana pun yang menyimpannya: gudang
     * asal bahan dulu, lalu gudang lain.
     *
     * @return float nilai yang keluar
     */
    public function issueUnitsFromAnywhere(Material $material, float $qty, string $type, ?Model $source, CarbonInterface|string $date, ?string $notes = null): float
    {
        $material->load('stocks.warehouse', 'warehouse');

        if ($qty > (float) $material->stock + 0.0001) {
            throw new RuntimeException(sprintf('Stok %s tidak cukup: ada %s, butuh %s %s.', $material->label(), qty((float) $material->stock), qty($qty), $material->stockUnit()));
        }

        $stocks = $material->stocks->where('qty', '>', 0)->sortBy(fn (MaterialStock $stock) => $stock->warehouse_id === $material->warehouse_id ? 0 : 1);
        $remaining = $qty;
        $cost = 0.0;

        foreach ($stocks as $stock) {
            if ($remaining <= 0.0001) {
                break;
            }

            $take = min($remaining, (float) $stock->qty);
            $cost += $this->issueUnits($material, $stock->warehouse, $take, $type, $source, $date, $notes);
            $remaining -= $take;
        }

        return $cost;
    }

    /** Catat gerak satu potongan pipa (masuk/keluar gudang) tanpa menghitung ulang. */
    public function recordPiece(PipePiece $piece, Warehouse $warehouse, string $type, float $in, float $out, ?Model $source, CarbonInterface|string $date, ?string $notes = null): MaterialMovement
    {
        return $this->record($piece->material, $warehouse, $piece, $type, $in, $out, (float) $piece->cost_per_cm, $source, $date, $notes);
    }

    /** Hapus semua gerak stok milik satu dokumen lalu hitung ulang bahannya. */
    public function forget(Model $source): void
    {
        $query = MaterialMovement::where('source_type', $source->getMorphClass())->where('source_id', $source->getKey());
        $materialIds = $query->pluck('material_id')->unique();
        $query->delete();

        Material::whereIn('id', $materialIds)->get()->each(fn (Material $material) => $this->recalculate($material));
    }

    /**
     * Hitung ulang stok per gudang, nilai, harga rata-rata, dan saldo berjalan
     * kartu stok dari awal.
     */
    public function recalculate(Material $material): void
    {
        DB::transaction(function () use ($material) {
            $movements = MaterialMovement::where('material_id', $material->id)->orderBy('moved_at')->orderBy('id')->get();
            $balances = [];
            $total = 0.0;
            $avg = 0.0;

            foreach ($movements as $movement) {
                $in = (float) $movement->qty_in;
                $out = (float) $movement->qty_out;
                $warehouseId = $movement->warehouse_id;

                if (! $material->isPipe() && $in > 0 && $movement->type !== 'sisa') {
                    $avg = $total > 0 ? (($total * $avg) + ($in * (float) $movement->unit_cost)) / ($total + $in) : (float) $movement->unit_cost;
                }

                $balances[$warehouseId] = ($balances[$warehouseId] ?? 0) + $in - $out;
                $total += $in - $out;

                if (round((float) $movement->balance_after, 3) !== round($balances[$warehouseId], 3)) {
                    $movement->forceFill(['balance_after' => $balances[$warehouseId]])->saveQuietly();
                }
            }

            if ($material->isPipe()) {
                $pieces = PipePiece::where('material_id', $material->id)->where('status', PipePiece::AVAILABLE)->get();
                $rows = $pieces->groupBy('warehouse_id')->map(fn ($group) => [
                    'qty' => (float) $group->sum('length_cm'),
                    'value' => (float) $group->sum(fn (PipePiece $piece) => $piece->value()),
                ]);
                $length = (float) $pieces->sum('length_cm');
                $avg = $length > 0 ? (float) $pieces->sum(fn (PipePiece $piece) => $piece->value()) / $length : (float) $material->avg_cost;
            } else {
                $rows = collect($balances)->map(fn (float $qty) => ['qty' => $qty, 'value' => $qty * $avg]);
            }

            MaterialStock::where('material_id', $material->id)->whereNotIn('warehouse_id', $rows->keys())->delete();

            foreach ($rows as $warehouseId => $row) {
                MaterialStock::updateOrCreate(['material_id' => $material->id, 'warehouse_id' => $warehouseId], $row);
            }

            $material->forceFill([
                'stock' => (float) $rows->sum('qty'),
                'avg_cost' => $avg,
            ])->save();
            $material->unsetRelation('stocks');
        });
    }

    private function record(Material $material, Warehouse $warehouse, ?PipePiece $piece, string $type, float $in, float $out, float $unitCost, ?Model $source, CarbonInterface|string $date, ?string $notes): MaterialMovement
    {
        return MaterialMovement::create([
            'material_id' => $material->id,
            'warehouse_id' => $warehouse->id,
            'pipe_piece_id' => $piece?->id,
            'type' => $type,
            'qty_in' => $in,
            'qty_out' => $out,
            'unit_cost' => $unitCost,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'moved_at' => Carbon::parse($date),
            'notes' => $notes,
        ]);
    }
}
