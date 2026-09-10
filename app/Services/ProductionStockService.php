<?php

namespace App\Services;

use App\Models\ProductionItem;
use App\Models\ProductionItemMovement;
use App\Models\ProductionItemOpname;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya tempat yang boleh menulis kartu stok barang produksi.
 *
 * Nota berstatus draft tidak menyentuh apa pun — angka baru nyata setelah
 * dibukukan. Membatalkannya menghapus jejak dokumen itu lalu menghitung ulang
 * dari baris yang tersisa; menghitung ulang lebih aman daripada menulis mutasi
 * kebalikan, karena hasilnya tetap benar walau ada dokumen lain yang dibukukan
 * sesudahnya.
 *
 * Sejak ada gudang, tiap baris kartu menyebut gudangnya, dan stok punya dua
 * wajah: total di barang, rincian per gudang. Keduanya sama-sama diturunkan
 * dari kartu yang sama, jadi tidak bisa berselisih.
 */
class ProductionStockService
{
    public function postOpname(ProductionItemOpname $opname): void
    {
        if ($opname->isPosted()) {
            throw new RuntimeException('Stok opname ini sudah dibukukan.');
        }

        $opname->loadMissing(['items.item', 'warehouse']);

        if (! $opname->warehouse) {
            throw new RuntimeException('Pilih gudang yang dihitung dulu — koreksi stok harus tahu masuk ke gudang mana.');
        }

        if ($opname->items->isEmpty()) {
            throw new RuntimeException('Tidak bisa membukukan opname tanpa baris barang.');
        }

        DB::transaction(function () use ($opname) {
            foreach ($opname->items as $baris) {
                $selisih = (float) $baris->difference;

                // Cocok dengan catatan: tidak ada yang perlu dikoreksi, dan
                // tidak perlu meninggalkan baris kartu yang kosong makna.
                if (abs($selisih) < 0.0001) {
                    continue;
                }

                $this->catat(
                    item: $baris->item,
                    gudang: $opname->warehouse,
                    selisih: $selisih,
                    source: $opname,
                    movedAt: $opname->opname_date,
                    notes: 'Stok opname '.$opname->opname_number.' — '.$opname->warehouse->name,
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
     *
     * `balance_after` adalah saldo GUDANG itu sesudah baris ini, bukan total
     * seluruh gudang — kartu stok dibaca orang yang sedang berdiri di satu
     * gudang, dan saldo total tidak menjawab pertanyaannya.
     */
    private function catat(
        ProductionItem $item,
        Warehouse $gudang,
        float $selisih,
        object $source,
        mixed $movedAt,
        ?string $notes = null,
    ): void {
        ProductionItemMovement::create([
            'production_item_id' => $item->id,
            'warehouse_id' => $gudang->id,
            'type' => 'opname',
            'qty_in' => max($selisih, 0.0),
            'qty_out' => max(-$selisih, 0.0),
            'balance_after' => $item->stockIn($gudang->id) + $selisih,
            'unit_cost' => $item->basePrice(),
            'source_type' => $source::class,
            'source_id' => $source->id,
            'moved_at' => $movedAt,
            'notes' => $notes,
        ]);

        // Total dan rinciannya sama-sama diturunkan ulang dari kartu, supaya
        // tidak ada jalan bagi keduanya untuk berselisih.
        $item->recalculateStock();
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
