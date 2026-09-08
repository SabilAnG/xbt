<?php

namespace App\Support;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Aksi hapus yang menolak menghapus data yang masih dipakai.
 *
 * Menghapus nota yang sudah dibukukan akan meninggalkan baris kartu stok dan
 * mutasi kas tanpa induk, sehingga stok dan saldo ikut melenceng. Menghapus
 * master data yang dipakai transaksi juga akan ditolak database lewat foreign
 * key — lebih baik dicegat di sini dengan pesan yang jelas.
 */
class TableActions
{
    /**
     * @param  callable(Model): ?string  $reason  Kembalikan alasan bila tidak boleh dihapus, null bila boleh.
     */
    public static function delete(callable $reason): DeleteAction
    {
        return DeleteAction::make()
            ->label('Hapus')
            ->modalHeading('Hapus data ini?')
            ->modalDescription('Tindakan ini tidak bisa dibatalkan.')
            ->before(function (Model $record, DeleteAction $action) use ($reason) {
                if ($why = $reason($record)) {
                    Notification::make()
                        ->danger()
                        ->title('Tidak bisa dihapus')
                        ->body($why)
                        ->persistent()
                        ->send();

                    $action->cancel();
                }
            });
    }

    /**
     * Versi massal: baris yang terkunci dilewati, sisanya tetap dihapus.
     *
     * @param  callable(Model): ?string  $reason
     */
    public static function deleteBulk(callable $reason): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->label('Hapus terpilih')
            ->before(function ($records, DeleteBulkAction $action) use ($reason) {
                $terkunci = collect($records)->filter(fn (Model $r) => $reason($r) !== null);

                if ($terkunci->isEmpty()) {
                    return;
                }

                if ($terkunci->count() === count($records)) {
                    Notification::make()
                        ->danger()
                        ->title('Tidak ada yang bisa dihapus')
                        ->body('Semua baris terpilih masih dipakai atau sudah dibukukan.')
                        ->persistent()
                        ->send();

                    $action->cancel();

                    return;
                }

                Notification::make()
                    ->warning()
                    ->title($terkunci->count().' baris dilewati')
                    ->body('Baris yang masih dipakai atau sudah dibukukan tidak ikut dihapus.')
                    ->send();

                // Sisakan hanya yang aman untuk dihapus.
                $action->records(collect($records)->reject(fn (Model $r) => $reason($r) !== null));
            });
    }

    /**
     * Alasan baku untuk nota transaksi: hanya draft yang boleh dihapus.
     */
    public static function onlyDraft(): callable
    {
        return fn (Model $record) => ($record->status ?? 'draft') === 'posted'
            ? 'Nota ini sudah dibukukan. Batalkan pembukuannya dulu agar stok dan kas dikembalikan, baru bisa dihapus.'
            : null;
    }

    /**
     * Alasan baku untuk master data: tolak bila masih direferensikan.
     *
     * @param  array<string, string>  $relations  nama relasi => label untuk pesan
     */
    public static function notInUse(array $relations): callable
    {
        return function (Model $record) use ($relations) {
            foreach ($relations as $relation => $label) {
                $count = $record->{$relation}()->count();

                if ($count > 0) {
                    return "Masih dipakai oleh {$count} {$label}. Pindahkan atau hapus data itu dulu.";
                }
            }

            return null;
        };
    }
}
