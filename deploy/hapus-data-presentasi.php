<?php

/**
 * Mencabut data presentasi yang dibuat PresentasiProduksiSeeder dan
 * PresentasiPenjualanSeeder.
 *
 * Yang dihapus hanya dokumen yang tercatat di kedua manifes di storage/app —
 * bukan tebakan berdasarkan tanggal atau nama, sehingga transaksi asli yang
 * dibuat belakangan tidak ikut terbawa.
 *
 * Urutannya dijaga dan penting: penjualan dulu (mengembalikan barang jadi ke
 * stok), lalu nota produksi (mengembalikan bahan dan menarik barang jadi), lalu
 * opname, baru pembelian. Terbalik sedikit saja, stok jadi minus di tengah
 * jalan dan pembatalannya ditolak.
 *
 * Jalankan: php deploy/hapus-data-presentasi.php [--dry-run]
 */

use App\Models\Formula;
use App\Models\FormulaCost;
use App\Models\FormulaMachine;
use App\Models\FormulaMaterial;
use App\Models\Item;
use App\Models\MaterialOpname;
use App\Models\MaterialPurchase;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Wallet;
use App\Services\PostingService;
use App\Services\ProductionPostingService;
use Database\Seeders\PresentasiPenjualanSeeder;
use Database\Seeders\PresentasiProduksiSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$kering = in_array('--dry-run', $argv, true);
$berkas = PresentasiProduksiSeeder::MANIFEST;
$berkasJual = PresentasiPenjualanSeeder::MANIFEST;

if (! Storage::disk('local')->exists($berkas) && ! Storage::disk('local')->exists($berkasJual)) {
    echo 'Manifes tidak ada — tidak ada data presentasi yang tercatat.'.PHP_EOL;
    exit(0);
}

/*
 * Penjualan dicabut lebih dulu.
 *
 * Nota penjualan mengeluarkan barang jadi dari stok. Kalau produksi dibatalkan
 * duluan, stok barang jadi jadi kurang dan pembatalannya ditolak — memang
 * begitu penjaganya bekerja.
 */
$penjualan = Storage::disk('local')->exists($berkasJual)
    ? (json_decode(Storage::disk('local')->get($berkasJual), true)['penjualan'] ?? [])
    : [];

if ($penjualan !== []) {
    echo '== PENJUALAN PRESENTASI =='.PHP_EOL;
    printf("  penjualan   %d: %s\n", count($penjualan), implode(', ', $penjualan));

    if (! $kering) {
        $postingJual = app(PostingService::class);

        foreach ($penjualan as $nomor) {
            $nota = Sale::where('invoice_number', $nomor)->first();

            if (! $nota) {
                continue;
            }

            if ($nota->isPosted()) {
                $postingJual->unpostSale($nota);
            }

            DB::transaction(function () use ($nota) {
                $nota->items()->delete();
                $nota->delete();
            });
        }

        Storage::disk('local')->delete($berkasJual);
        echo '  '.count($penjualan).' nota penjualan dicabut.'.PHP_EOL;
    }

    echo PHP_EOL;
}

if (! Storage::disk('local')->exists($berkas)) {
    echo 'Manifes produksi tidak ada — selesai.'.PHP_EOL;
    exit(0);
}

$manifes = json_decode(Storage::disk('local')->get($berkas), true);
$isi = $manifes['isi'] ?? [];

echo '== '.($kering ? 'SIMULASI' : 'PENCABUTAN').' DATA PRESENTASI =='.PHP_EOL;
echo '   dibuat '.($manifes['dibuat_pada'] ?? '-').PHP_EOL.PHP_EOL;

foreach ($isi as $jenis => $daftar) {
    if (! $daftar) {
        continue;
    }

    // saldo_awal berisi baris array, bukan nomor dokumen.
    $ringkas = collect($daftar)
        ->map(fn ($x) => is_array($x) ? 'dompet #'.($x['wallet_id'] ?? '?') : (string) $x)
        ->implode(', ');

    printf("  %-11s %d: %s\n", $jenis, count($daftar), $ringkas);
}

// Barang jadi yang sudah terjual membuat pembatalan produksi ditolak — lebih
// baik ketahuan sekarang daripada berhenti di tengah jalan.
//
// Penjualan presentasi sendiri tidak dihitung: ia justru sudah dicabut di
// langkah sebelumnya (atau akan dicabut, kalau ini baru simulasi).
$idPenjualanDemo = $penjualan !== []
    ? Sale::whereIn('invoice_number', $penjualan)->pluck('id')->all()
    : [];

$masalah = [];

foreach ($isi['item'] ?? [] as $sku) {
    $item = Item::where('sku', $sku)->first();

    if (! $item) {
        continue;
    }

    $terjual = StockMovement::where('item_id', $item->id)
        ->where('type', '!=', 'produksi')
        ->where('qty_out', '>', 0)
        ->when($idPenjualanDemo !== [], fn ($q) => $q->where(
            fn ($w) => $w->where('source_type', '!=', Sale::class)
                ->orWhereNotIn('source_id', $idPenjualanDemo)
        ))
        ->count();

    if ($terjual > 0) {
        $masalah[] = "Barang {$sku} sudah punya {$terjual} mutasi keluar di luar produksi dan di luar data presentasi.";
    }
}

if ($masalah !== []) {
    echo PHP_EOL.'== TIDAK BISA DICABUT =='.PHP_EOL;
    foreach ($masalah as $m) {
        echo '  '.$m.PHP_EOL;
    }
    echo PHP_EOL.'Batalkan dulu transaksi yang memakainya.'.PHP_EOL;
    exit(1);
}

if ($kering) {
    echo PHP_EOL.'Simulasi selesai. Jalankan tanpa --dry-run untuk benar-benar mencabut.'.PHP_EOL;
    exit(0);
}

$posting = app(ProductionPostingService::class);
$dicabut = [];

// 1. Nota produksi: batalkan dulu supaya bahan kembali dan barang jadi ditarik.
foreach ($isi['produksi'] ?? [] as $nomor) {
    $nota = Production::where('production_number', $nomor)->first();

    if (! $nota) {
        continue;
    }

    if ($nota->isPosted()) {
        $posting->unpostProduction($nota);
    }

    DB::transaction(function () use ($nota) {
        $nota->materials()->delete();
        $nota->costs()->delete();
        $nota->machines()->delete();
        $nota->delete();
    });

    $dicabut[] = $nomor;
}

// 2. Stok opname.
foreach ($isi['opname'] ?? [] as $nomor) {
    $op = MaterialOpname::where('opname_number', $nomor)->first();

    if (! $op) {
        continue;
    }

    if ($op->isPosted()) {
        $posting->unpostMaterialOpname($op);
    }

    DB::transaction(function () use ($op) {
        $op->items()->delete();
        $op->delete();
    });

    $dicabut[] = $nomor;
}

// 3. Pembelian bahan.
foreach ($isi['pembelian'] ?? [] as $nomor) {
    $nota = MaterialPurchase::where('invoice_number', $nomor)->first();

    if (! $nota) {
        continue;
    }

    if ($nota->isPosted()) {
        $posting->unpostPurchase($nota);
    }

    DB::transaction(function () use ($nota) {
        $nota->items()->delete();
        $nota->delete();
    });

    $dicabut[] = $nomor;
}

// 4. Rencana produksi.
foreach ($isi['rencana'] ?? [] as $nomor) {
    $rencana = ProductionPlan::where('plan_number', $nomor)->first();

    if (! $rencana) {
        continue;
    }

    DB::transaction(function () use ($rencana) {
        $rencana->lines()->delete();
        $rencana->delete();
    });

    $dicabut[] = $nomor;
}

// 5. Formula tambahan beserta barisnya. Formula bawaan tidak disentuh.
foreach ($isi['formula'] ?? [] as $kode) {
    $f = Formula::where('code', $kode)->first();

    if (! $f) {
        continue;
    }

    DB::transaction(function () use ($f) {
        FormulaMaterial::where('formula_id', $f->id)->delete();
        FormulaCost::where('formula_id', $f->id)->delete();
        FormulaMachine::where('formula_id', $f->id)->delete();
        $f->delete();
    });

    $dicabut[] = $kode;
}

// 6. Barang jual. Formula bawaan yang menunjuk ke sini dilepas dulu.
foreach ($isi['item'] ?? [] as $sku) {
    $item = Item::where('sku', $sku)->first();

    if (! $item) {
        continue;
    }

    DB::transaction(function () use ($item) {
        Formula::where('item_id', $item->id)->update(['item_id' => null]);
        StockMovement::where('item_id', $item->id)->delete();
        $item->delete();
    });

    $dicabut[] = $sku;
}

// 7. Saldo awal dompet dikembalikan seperti semula.
if ($modal = $manifes['isi']['saldo_awal'] ?? null) {
    foreach ($modal as $baris) {
        $w = Wallet::find($baris['wallet_id'] ?? 0);

        if ($w) {
            $w->forceFill(['opening_balance' => $baris['sebelumnya']])->save();
            $w->recalculateBalance();
            $dicabut[] = 'saldo awal '.$w->name;
        }
    }
}

Storage::disk('local')->delete($berkas);

echo PHP_EOL.'Dicabut: '.count($dicabut).' catatan.'.PHP_EOL.PHP_EOL;

echo '== SISA =='.PHP_EOL;
printf("  formula %d · barang jual %d · pembelian %d · produksi %d · rencana %d · opname %d\n",
    Formula::count(), Item::count(), MaterialPurchase::count(),
    Production::count(), ProductionPlan::count(), MaterialOpname::count());
