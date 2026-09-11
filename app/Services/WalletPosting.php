<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;

/**
 * Satu-satunya tempat yang boleh menulis mutasi kas.
 *
 * Dulu ini bagian dalam PostingService. Dipisah ketika pembelian bahan produksi
 * ikut menyentuh dompet: dua tempat yang sama-sama boleh menulis saldo adalah
 * dua tempat yang bisa berselisih, dan selisih saldo kas tidak pernah ketahuan
 * cepat.
 */
class WalletPosting
{
    /**
     * Catat satu mutasi. Dompet boleh kosong — nota bisa dicatat tanpa
     * menyebut sumber dananya, dan saat itu kas memang tidak bergerak.
     */
    public function move(
        ?Wallet $wallet,
        string $direction,
        float $amount,
        object $source,
        mixed $occurredAt,
        ?string $description = null,
    ): void {
        if (! $wallet || abs($amount) < 0.0001) {
            return;
        }

        $balance = $direction === 'in'
            ? (float) $wallet->current_balance + $amount
            : (float) $wallet->current_balance - $amount;

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balance,
            'source_type' => $source::class,
            'source_id' => $source->id,
            'occurred_at' => $occurredAt,
            'description' => $description,
        ]);

        $wallet->forceFill(['current_balance' => $balance])->save();
    }

    /**
     * Hapus jejak kas sebuah dokumen, lalu hitung ulang saldo yang terdampak.
     *
     * Menghitung ulang, bukan menulis mutasi kebalikan: hasilnya tetap benar
     * walau ada nota lain yang dibukukan sesudahnya.
     */
    public function reverseFor(object $document): void
    {
        $walletIds = WalletTransaction::query()
            ->where('source_type', $document::class)
            ->where('source_id', $document->id)
            ->pluck('wallet_id')
            ->unique();

        WalletTransaction::query()
            ->where('source_type', $document::class)
            ->where('source_id', $document->id)
            ->delete();

        Wallet::whereIn('id', $walletIds)->get()->each->recalculateBalance();
    }
}
