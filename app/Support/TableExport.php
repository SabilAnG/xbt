<?php

namespace App\Support;

use Filament\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tombol "Export CSV" untuk tabel resource.
 *
 * Yang diekspor adalah hasil query yang SEDANG DIFILTER di layar, bukan seluruh
 * isi tabel — jadi kalau pengguna menyaring "Penjualan bulan ini yang sudah
 * dibukukan", itu juga yang turun ke berkas.
 *
 * Sengaja tidak memakai fitur Export bawaan Filament: fitur itu menuntut tabel
 * `exports` dan worker queue yang berjalan. Di server ini belum ada worker, dan
 * tombol yang mengantre selamanya lebih buruk daripada unduhan langsung.
 */
class TableExport
{
    /**
     * Penanda UTF-8 di awal berkas. Tanpa ini Excel membaca berkas sebagai
     * ANSI, sehingga huruf beraksen dan simbol rupiah tampil rusak.
     */
    public const BOM = "\xEF\xBB\xBF";

    /**
     * @param  string  $prefix  awalan nama berkas, mis. "penjualan"
     * @param  array<string, callable(mixed): mixed>  $columns  judul kolom => cara mengambil nilainya
     */
    public static function action(string $prefix, array $columns): Action
    {
        return Action::make('exportCsv')
            ->label('Export CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function ($livewire) use ($prefix, $columns): StreamedResponse {
                $records = $livewire->getFilteredSortedTableQuery()->get();

                $filename = sprintf('%s-%s.csv', $prefix, now()->format('Ymd-Hi'));

                return response()->streamDownload(function () use ($records, $columns) {
                    $out = fopen('php://output', 'wb');

                    fwrite($out, self::BOM);

                    fputcsv($out, array_keys($columns));

                    foreach ($records as $record) {
                        $row = [];
                        foreach ($columns as $get) {
                            $value = $get($record);
                            $row[] = $value instanceof \DateTimeInterface
                                ? $value->format('Y-m-d')
                                : $value;
                        }
                        fputcsv($out, $row);
                    }

                    fclose($out);
                }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }
}
