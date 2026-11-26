<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\PipePiece;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pindah barang antar gudang. Tiap baris jadi dua gerak di kartu stok
 * (keluar dari gudang asal, masuk ke gudang tujuan) dengan harga yang sama,
 * jadi total stok dan nilai persediaan tidak berubah.
 */
class StockTransferService
{
    public function __construct(private StockService $stock) {}

    /** Gudang yang bisa menjadi asal/tujuan pindah barang. */
    public static function warehouseTypes(): array
    {
        return [Warehouse::RAW, Warehouse::SEMI, Warehouse::ACCESSORY, Warehouse::OFFCUT];
    }

    /**
     * @param  array{transferred_at: string, from_warehouse_id: int, to_warehouse_id: int, moved_by?: ?string, notes?: ?string, items?: list<array{material_id: int, qty: float}>, pieces?: list<int>}  $data
     */
    public function create(array $data): StockTransfer
    {
        $from = Warehouse::findOrFail($data['from_warehouse_id']);
        $to = Warehouse::findOrFail($data['to_warehouse_id']);
        $allowed = collect(self::warehouseTypes())->map(fn (string $type) => Warehouse::forType($type)->id);

        if ($from->is($to)) {
            throw new RuntimeException('Gudang asal dan tujuan tidak boleh sama.');
        }

        if (! $allowed->contains($from->id) || ! $allowed->contains($to->id)) {
            throw new RuntimeException('Pindah barang hanya antar gudang penyimpanan (Bahan Baku, Setengah Jadi, Aksesoris, Stok Sisa).');
        }

        $items = collect($data['items'] ?? [])->filter(fn (array $row) => filled($row['material_id'] ?? null) && (float) ($row['qty'] ?? 0) > 0);
        $pieceIds = collect($data['pieces'] ?? [])->filter()->map(fn ($id) => (int) $id)->unique();

        if ($items->isEmpty() && $pieceIds->isEmpty()) {
            throw new RuntimeException('Pilih minimal satu barang atau potongan pipa yang dipindah.');
        }

        return DB::transaction(function () use ($data, $from, $to, $items, $pieceIds) {
            $date = Carbon::parse($data['transferred_at']);
            $transfer = StockTransfer::create([
                'transfer_number' => DocumentNumber::next('stock_transfers', $date),
                'transferred_at' => $date->toDateString(),
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'moved_by' => $data['moved_by'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $note = $transfer->transfer_number.' · '.$from->name.' → '.$to->name;

            foreach ($items->groupBy('material_id') as $materialId => $rows) {
                $material = Material::findOrFail($materialId);
                $qty = (float) $rows->sum('qty');

                if ($material->isPipe()) {
                    throw new RuntimeException("{$material->label()} adalah pipa: pindahkan per potongan.");
                }

                $this->assertAllowed($material, $to);
                $value = $this->stock->issueUnits($material, $from, $qty, 'pindah', $transfer, $date, $note);
                $this->stock->receiveUnits($material->fresh(), $to, $qty, $value / $qty, 'pindah', $transfer, $date, $note);
                $transfer->items()->create(['material_id' => $material->id, 'qty' => $qty, 'value' => $value]);
            }

            $pieces = PipePiece::query()->with('material')->whereIn('id', $pieceIds)->lockForUpdate()->get();

            foreach ($pieces as $piece) {
                if ($piece->status !== PipePiece::AVAILABLE || $piece->warehouse_id !== $from->id) {
                    throw new RuntimeException("Potongan {$piece->code} tidak tersedia di {$from->name}.");
                }

                $this->assertAllowed($piece->material, $to);
                $length = (float) $piece->length_cm;
                $this->stock->recordPiece($piece, $from, 'pindah', 0, $length, $transfer, $date, $note.' · '.$piece->code);
                $piece->forceFill(['warehouse_id' => $to->id])->save();
                $this->stock->recordPiece($piece, $to, 'pindah', $length, 0, $transfer, $date, $note.' · '.$piece->code);
                $transfer->items()->create(['material_id' => $piece->material_id, 'pipe_piece_id' => $piece->id, 'qty' => $length, 'value' => $piece->value()]);
            }

            $pieces->pluck('material')->unique('id')->each(fn (Material $material) => $this->stock->recalculate($material));

            return $transfer;
        });
    }

    /** Batalkan pindah barang: barang kembali ke gudang asal, bila belum dipakai di gudang tujuan. */
    public function delete(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $transfer->load('items.material.stocks', 'items.piece', 'toWarehouse');
            $lastOwn = (int) MaterialMovement::where('source_type', $transfer->getMorphClass())->where('source_id', $transfer->id)->max('id');

            foreach ($transfer->items as $line) {
                if ($line->piece) {
                    $movedSince = MaterialMovement::where('pipe_piece_id', $line->piece->id)->where('id', '>', $lastOwn)->exists();

                    if ($movedSince || $line->piece->status !== PipePiece::AVAILABLE || $line->piece->warehouse_id !== $transfer->to_warehouse_id) {
                        throw new RuntimeException("Potongan {$line->piece->code} sudah dipakai atau dipindah lagi, jadi pindah barang ini tidak bisa dibatalkan.");
                    }
                } elseif ($line->material->stockIn($transfer->to_warehouse_id) + 0.0001 < (float) $line->qty) {
                    throw new RuntimeException("Stok {$line->material->label()} di {$transfer->toWarehouse->name} sudah terpakai, jadi pindah barang ini tidak bisa dibatalkan.");
                }
            }

            foreach ($transfer->items->whereNotNull('pipe_piece_id') as $line) {
                $line->piece->forceFill(['warehouse_id' => $transfer->from_warehouse_id])->save();
            }

            $this->stock->forget($transfer);
            $transfer->items()->delete();
            $transfer->delete();
        });
    }

    private function assertAllowed(Material $material, Warehouse $to): void
    {
        if (! in_array($to->id, $material->allowedWarehouseIds(), true)) {
            throw new RuntimeException($material->isPipe()
                ? "{$material->label()}: pipa hanya bisa disimpan di Gudang Bahan Baku atau Gudang Stok Sisa."
                : "{$material->label()} tidak bisa disimpan di {$to->name}.");
        }
    }
}
