<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WalletTransfer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Buku kas dompet. Setiap dokumen (penjualan, pengeluaran, pembelian bahan,
 * transfer) punya mutasinya sendiri; sync() menghapus lalu membuat ulang
 * mutasi dokumen itu, sehingga mengubah nominal atau dompet selalu benar.
 * Saldo = saldo awal + masuk − keluar, dihitung ulang urut tanggal.
 */
class WalletService
{
    /**
     * Pasang (atau hapus) mutasi satu dokumen.
     */
    public function sync(Model $source, ?int $walletId, string $direction, float $amount, Carbon|string $date, ?string $description = null): void
    {
        DB::transaction(function () use ($source, $walletId, $direction, $amount, $date, $description) {
            $affected = $this->forget($source, recalculate: false);

            if ($walletId && $amount > 0) {
                WalletTransaction::create([
                    'wallet_id' => $walletId,
                    'direction' => $direction,
                    'amount' => $amount,
                    'source_type' => $source::class,
                    'source_id' => $source->getKey(),
                    'occurred_at' => Carbon::parse($date),
                    'description' => $description,
                ]);
                $affected[] = $walletId;
            }

            $this->recalculate($affected);
        });
    }

    /**
     * Hapus mutasi milik satu dokumen.
     *
     * @return list<int> id dompet yang terdampak
     */
    public function forget(Model $source, bool $recalculate = true): array
    {
        $query = WalletTransaction::where('source_type', $source::class)->where('source_id', $source->getKey());
        $ids = $query->pluck('wallet_id')->unique()->values()->all();
        $query->delete();

        if ($recalculate) {
            $this->recalculate($ids);
        }

        return $ids;
    }

    /** Mutasi manual: setor modal, tarik pribadi, koreksi saldo. */
    public function manual(Wallet $wallet, string $direction, float $amount, Carbon|string $date, string $description): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal harus lebih dari 0.');
        }

        return DB::transaction(function () use ($wallet, $direction, $amount, $date, $description) {
            $row = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'direction' => $direction,
                'amount' => $amount,
                'occurred_at' => Carbon::parse($date),
                'description' => $description,
            ]);
            $this->recalculate([$wallet->id]);

            return $row;
        });
    }

    public function transfer(Wallet $from, Wallet $to, float $amount, Carbon|string $date, ?string $notes = null): WalletTransfer
    {
        if ($from->is($to)) {
            throw new InvalidArgumentException('Dompet asal dan tujuan tidak boleh sama.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal transfer harus lebih dari 0.');
        }

        return DB::transaction(function () use ($from, $to, $amount, $date, $notes) {
            $transfer = WalletTransfer::create([
                'from_wallet_id' => $from->id, 'to_wallet_id' => $to->id,
                'amount' => $amount, 'transferred_at' => Carbon::parse($date)->toDateString(), 'notes' => $notes,
            ]);

            foreach ([[$from, 'out', 'Transfer ke '.$to->name], [$to, 'in', 'Transfer dari '.$from->name]] as [$wallet, $direction, $label]) {
                WalletTransaction::create([
                    'wallet_id' => $wallet->id, 'direction' => $direction, 'amount' => $amount,
                    'source_type' => WalletTransfer::class, 'source_id' => $transfer->id,
                    'occurred_at' => Carbon::parse($date), 'description' => trim($label.($notes ? ' — '.$notes : '')),
                ]);
            }

            $this->recalculate([$from->id, $to->id]);

            return $transfer;
        });
    }

    public function deleteTransfer(WalletTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $this->forget($transfer);
            $transfer->delete();
        });
    }

    public function deleteManual(WalletTransaction $row): void
    {
        if ($row->source_type !== null) {
            throw new InvalidArgumentException('Mutasi ini berasal dari transaksi lain; ubah atau hapus transaksinya.');
        }

        DB::transaction(function () use ($row) {
            $walletId = $row->wallet_id;
            $row->delete();
            $this->recalculate([$walletId]);
        });
    }

    /**
     * Hitung ulang saldo berjalan & saldo akhir dompet.
     *
     * @param  iterable<int>  $walletIds
     */
    public function recalculate(iterable $walletIds): void
    {
        foreach (collect($walletIds)->filter()->unique() as $walletId) {
            $wallet = Wallet::find($walletId);

            if (! $wallet) {
                continue;
            }

            $balance = (float) $wallet->opening_balance;

            foreach (WalletTransaction::where('wallet_id', $walletId)->orderBy('occurred_at')->orderBy('id')->get() as $row) {
                $balance += $row->direction === 'in' ? (float) $row->amount : -(float) $row->amount;

                if (round((float) $row->balance_after, 2) !== round($balance, 2)) {
                    $row->forceFill(['balance_after' => $balance])->saveQuietly();
                }
            }

            $wallet->forceFill(['current_balance' => $balance])->save();
        }
    }
}
