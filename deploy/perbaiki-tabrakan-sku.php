<?php

/**
 * Perbaikan sekali jalan untuk server yang sudah punya data demo produksi lama.
 *
 * Masalahnya: seeder mencocokkan bahan lewat SKU dan komponen biaya lewat slug.
 * Data demo lama memakai SKU "PLAT-12" dan slug "bending-pipa" untuk barang yang
 * berbeda, sehingga seeder memakai baris lama itu dan formula contoh jadi
 * merujuk bahan tanpa dimensi — HPP-nya membengkak jadi miliaran.
 *
 * Skrip ini HANYA mengganti nama/kode baris lama dan mengarahkan ulang baris
 * formula ke baris yang benar. Tidak ada baris yang dihapus.
 *
 * Aman dijalankan berulang: bila tidak ada yang perlu diperbaiki, ia diam saja.
 *
 * Jalankan: docker compose -f compose.prod.yaml exec app php deploy/perbaiki-tabrakan-sku.php
 */

use App\Models\CostComponent;
use App\Models\Formula;
use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Models\Production;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$log = function (string $pesan) {
    echo '  '.$pesan.PHP_EOL;
};

echo '== PERIKSA DULU =='.PHP_EOL;
$log(sprintf('nota produksi %d · nota pembelian bahan %d',
    Production::count(), MaterialPurchase::count()));

DB::transaction(function () use ($log) {
    // 1. Baris demo lama diberi kode baru supaya tidak lagi bentrok.
    $platLama = Material::where('sku', 'PLAT-12')->where('dimension_type', 'count')->first();

    if ($platLama) {
        $platLama->update([
            'sku' => 'PLAT-12-LAMA',
            'name' => rtrim($platLama->name).' (demo lama)',
        ]);
        $log('bahan demo lama "PLAT-12" -> "PLAT-12-LAMA"');
    }

    $bendingLama = CostComponent::where('slug', 'bending-pipa')
        ->where('rate_type', 'per_unit')->first();

    if ($bendingLama) {
        $bendingLama->update([
            'slug' => 'bending-pipa-lama',
            'name' => rtrim($bendingLama->name).' (demo lama)',
        ]);
        $log('komponen demo lama "bending-pipa" -> "bending-pipa-lama"');
    }
});

// 2. Seeder dijalankan ulang: sekarang baris yang benar bisa dibuat.
echo PHP_EOL.'== SUSUN ULANG MASTER PRODUKSI =='.PHP_EOL;
Artisan::call('db:seed', [
    '--class' => 'ProduksiSeeder', '--force' => true,
]);
$log('seeder selesai');

// 3. Baris formula yang telanjur merujuk baris lama diarahkan ke yang benar.
echo PHP_EOL.'== ARAHKAN ULANG BARIS FORMULA =='.PHP_EOL;

DB::transaction(function () use ($log) {
    $formula = Formula::with(['materials', 'costs'])->where('code', 'M3-STD-RACING-V1')->first();

    if (! $formula) {
        $log('formula contoh tidak ada — tidak ada yang perlu diarahkan');

        return;
    }

    $platBaru = Material::where('sku', 'PLAT-12')->where('dimension_type', 'sheet')->first();
    $platLama = Material::where('sku', 'PLAT-12-LAMA')->first();

    if ($platBaru && $platLama) {
        $n = $formula->materials()->where('material_id', $platLama->id)
            ->update(['material_id' => $platBaru->id]);
        $log($n.' baris bahan diarahkan ke '.$platBaru->name);
    }

    $bendingBaru = CostComponent::where('slug', 'bending-pipa')->where('rate_type', 'per_hour')->first();
    $bendingLama = CostComponent::where('slug', 'bending-pipa-lama')->first();

    if ($bendingBaru && $bendingLama) {
        $n = $formula->costs()->where('cost_component_id', $bendingLama->id)
            ->update(['cost_component_id' => $bendingBaru->id]);
        $log($n.' baris biaya diarahkan ke '.$bendingBaru->name);
    }
});

// 4. Hasilnya diperiksa, bukan diasumsikan.
echo PHP_EOL.'== HASIL =='.PHP_EOL;

$f = Formula::with(['materials.material', 'costs.component', 'machines.machine'])
    ->where('code', 'M3-STD-RACING-V1')->first();

if (! $f) {
    $log('formula contoh tidak ada');
    exit(1);
}

$rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
$b = $f->breakdown();

$log(sprintf('bahan %s · tenaga kerja %s (%s menit) · mesin %s · overhead %s',
    $rp($b['bahan']), $rp($b['jasa']), $b['menit'], $rp($b['mesin']), $rp($b['overhead'])));
$log('HPP per set: '.$rp($b['per_unit']));

$wajar = $b['per_unit'] > 100_000 && $b['per_unit'] < 2_000_000;
$log($wajar ? 'HPP masuk akal.' : 'HPP MASIH JANGGAL — periksa lagi bahan yang dipakai formula.');

if (! $wajar) {
    foreach ($f->materials as $l) {
        printf("    %-28s %-10s %s\n", $l->material?->name, $l->material?->dimension_type,
            $rp($l->subtotal()));
    }
}

exit($wajar ? 0 : 1);
