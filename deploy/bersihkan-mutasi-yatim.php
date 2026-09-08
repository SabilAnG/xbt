<?php

/**
 * Menghapus mutasi dompet dan kartu stok yang dokumen sumbernya sudah tidak ada.
 *
 * Mutasi seperti ini membuat saldo dan stok melenceng tanpa ada nota yang bisa
 * ditelusuri — di sini penyebabnya sisa pengujian yang notanya terhapus tapi
 * mutasinya tertinggal.
 *
 * Hanya baris yang benar-benar yatim yang dihapus: kelas sumbernya masih
 * dikenal, tapi datanya tidak ditemukan. Baris tanpa sumber sama sekali
 * (mis. penyesuaian manual) sengaja TIDAK disentuh.
 *
 * Jalankan --dry-run dulu:
 *   php deploy/bersihkan-mutasi-yatim.php --dry-run
 */

use App\Models\MaterialMovement;
use App\Models\StockMovement;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$kering = in_array('--dry-run', $argv, true);

/**
 * @param  class-string<Model>  $kelas
 * @return array<int, Model>
 */
$cariYatim = function (string $kelas, string $label) {
    $yatim = [];

    foreach ($kelas::query()->cursor() as $baris) {
        $tipe = $baris->source_type;

        // Tanpa sumber sama sekali bukan yatim — bisa jadi penyesuaian manual.
        if (! $tipe || ! class_exists($tipe)) {
            continue;
        }

        if ($tipe::find($baris->source_id) === null) {
            $yatim[] = $baris;
        }
    }

    printf("  %-22s %d yatim dari %d baris\n", $label, count($yatim), $kelas::count());

    return $yatim;
};

echo '== '.($kering ? 'SIMULASI' : 'PEMBERSIHAN').' MUTASI YATIM =='.PHP_EOL.PHP_EOL;

$dompet = $cariYatim(WalletTransaction::class, 'mutasi dompet');
$barang = $cariYatim(StockMovement::class, 'kartu stok barang');
$bahan = $cariYatim(MaterialMovement::class, 'kartu stok bahan');

$total = count($dompet) + count($barang) + count($bahan);

if ($total === 0) {
    echo PHP_EOL.'Tidak ada mutasi yatim. Tidak ada yang perlu dibersihkan.'.PHP_EOL;
    exit(0);
}

echo PHP_EOL.'Rinciannya:'.PHP_EOL;

foreach ($dompet as $t) {
    printf("  dompet  #%-4d %-4s %-14s %s\n", $t->id, $t->direction,
        number_format((float) $t->amount, 0, ',', '.'), $t->description);
}

foreach (array_merge($barang, $bahan) as $m) {
    printf("  stok    #%-4d masuk %s keluar %s  %s\n", $m->id,
        rtrim(rtrim((string) $m->qty_in, '0'), '.'),
        rtrim(rtrim((string) $m->qty_out, '0'), '.'), $m->notes);
}

if ($kering) {
    echo PHP_EOL."Simulasi: {$total} baris akan dihapus. Jalankan tanpa --dry-run untuk membersihkan.".PHP_EOL;
    exit(0);
}

$dompetTerdampak = collect($dompet)->pluck('wallet_id')->unique()->all();

DB::transaction(function () use ($dompet, $barang, $bahan) {
    foreach (array_merge($dompet, $barang, $bahan) as $baris) {
        $baris->delete();
    }
});

// Saldo cache dihitung ulang dari mutasi yang tersisa.
foreach (Wallet::whereIn('id', $dompetTerdampak)->get() as $w) {
    $sebelum = (float) $w->current_balance;
    $w->recalculateBalance();
    printf("\n  %s: %s -> %s\n", $w->name,
        'Rp '.number_format($sebelum, 0, ',', '.'),
        'Rp '.number_format((float) $w->fresh()->current_balance, 0, ',', '.'));
}

echo PHP_EOL."Dibersihkan: {$total} baris.".PHP_EOL;
