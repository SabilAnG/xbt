<?php

namespace App\Services;

use App\Models\ProductionItem;
use App\Models\ProductionItemMovement;
use App\Models\ProductionItemOpname;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya tempat yang boleh menulis kartu stok barang produksi.
 *
 * Nota berstatus draft tidak menyentuh apa pun — angka baru nyata setelah
 * dibukukan. Membatalkannya menghapus jejak dokumen itu lalu menghitung ulang
 * stok dari baris yang tersisa; menghitung ulang lebih aman daripada menulis
 * mutasi kebalikan, karena hasilnya tetap benar walau ada dokumen lain yang
 * dibukukan sesudahnya.
 */
class ProductionStockService
{
    public function postOpname(ProductionItemOpname $opname): void
    {
        if ($opname->isPosted()) {
            throw new RuntimeException('Stok opname ini sudah dibukukan.');
        }

        $opname->loadMissing('items.item');

        if ($opname->items->isEmpty()) {
            throw new RuntimeException('Tidak bisa membukukan opname tanpa baris barang.');
        }

        DB::transaction(function () use ($opname) {
            foreach ($opname->items as $baris) {
                $selisih = (float) $baris->difference;

                // Cocok dengan catatan: tidak ada yang perlu dikoreksi, dan
                // tidak perlu meninggalkan baris kartu stok yang kosong makna.
                if (abs($selisih) < 0.0001) {
                    continue;
                }

                $this->catat(
                    item: $baris->item,
                    selisih: $selisih,
                    source: $opname,
                    movedAt: $opname->opname_date,
                    notes: 'Stok opname '.$opname->opname_number,
                );
            }

            $opname->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
        });
    }

    public function unpostOpname(ProductionItemOpname $opname): void
    {
        DB::transaction(function () use ($opname) {
            $this->reverse($opname);
            $opname->forceFill(['status' => 'draft', 'posted_at' => null])->save();
        });
    }

    /**
     * Tulis satu baris kartu stok. Selisih positif menambah, negatif mengurangi.
     */
    private function catat(
        ProductionItem $item,
        float $selisih,
        object $source,
        mixed $movedAt,
        ?string $notes = null,
    ): void {
        $masuk = max($selisih, 0.0);
        $keluar = max(-$selisih, 0.0);
        $saldo = (float) $item->stock + $selisih;

        ProductionItemMovement::create([
            'production_item_id' => $item->id,
            'type' => 'opname',
            'qty_in' => $masuk,
            'qty_out' => $keluar,
            'balance_after' => $saldo,
            'unit_cost' => $item->basePrice(),
            'source_type' => $source::class,
            'source_id' => $source->id,
            'moved_at' => $movedAt,
            'notes' => $notes,
        ]);

        $item->forceFill(['stock' => $saldo])->save();
    }

    /** Hapus jejak sebuah dokumen, lalu hitung ulang stok yang terdampak. */
    private function reverse(object $document): void
    {
        $itemIds = ProductionItemMovement::query()
            ->where('source_type', $document::class)
            ->where('source_id', $document->id)
            ->pluck('production_item_id')
            ->unique();

        ProductionItemMovement::query()
            ->where('source_type', $document::class)
            ->where('source_id', $document->id)
            ->delete();

        ProductionItem::whereIn('id', $itemIds)->get()->each->recalculateStock();
    }
}
