<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nomor nota berurutan per bulan, mis. PB-202609-0007.
 *
 * Nomor diambil dari tabel document_counters lewat baris yang dikunci
 * (SELECT ... FOR UPDATE). Cara lama — membaca nomor terakhir lalu menambah
 * satu — bisa memberi nomor kembar kalau dua kasir menyimpan bersamaan.
 * Unique index pada kolom nomor tetap dipertahankan sebagai penjaga terakhir.
 */
class DocumentNumber
{
    private const PREFIXES = [
        'purchases' => 'PB',
        'sales' => 'PJ',
        'expenses' => 'PG',
        'stock_opnames' => 'SO',
        // Modul produksi
        'production_item_opnames' => 'SOP',  // stok opname barang produksi
        'production_purchases' => 'PBP',     // pembelian bahan produksi
    ];

    private const COLUMNS = [
        'purchases' => 'invoice_number',
        'sales' => 'invoice_number',
        'expenses' => 'reference_number',
        'stock_opnames' => 'opname_number',
        'production_item_opnames' => 'opname_number',
        'production_purchases' => 'invoice_number',
    ];

    /**
     * Ambil nomor berikutnya dan naikkan counternya.
     */
    public static function next(string $table, ?\DateTimeInterface $date = null): string
    {
        $prefix = self::PREFIXES[$table] ?? 'DOC';
        $period = ($date ? Carbon::instance($date) : now())->format('Ym');
        $stem = "{$prefix}-{$period}-";

        $sequence = DB::transaction(function () use ($table, $period) {
            $row = DB::table('document_counters')
                ->where('document', $table)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                // Mulai dari nomor tertinggi yang sudah ada, supaya penomoran
                // tidak mengulang bila tabel counter dibuat setelah ada data.
                $start = self::highestExisting($table, $period);

                DB::table('document_counters')->insert([
                    'document' => $table,
                    'period' => $period,
                    'last_number' => $start + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $start + 1;
            }

            DB::table('document_counters')
                ->where('id', $row->id)
                ->update(['last_number' => $row->last_number + 1, 'updated_at' => now()]);

            return $row->last_number + 1;
        });

        return $stem.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private static function highestExisting(string $table, string $period): int
    {
        $column = self::COLUMNS[$table] ?? 'invoice_number';
        $prefix = self::PREFIXES[$table] ?? 'DOC';
        $stem = "{$prefix}-{$period}-";

        $last = DB::table($table)
            ->where($column, 'like', $stem.'%')
            ->orderByDesc($column)
            ->value($column);

        return $last ? (int) substr($last, strlen($stem)) : 0;
    }
}
