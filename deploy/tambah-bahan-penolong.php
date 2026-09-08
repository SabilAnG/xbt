<?php

/**
 * Menambahkan bahan penolong ke formula contoh yang sudah terlanjur dibuat.
 *
 * Seeder sengaja tidak menimpa formula yang sudah ada, jadi formula lama tidak
 * ikut mendapat baris kawat las, gas, mata gerinda, amplas, dan compound poles
 * ketika pos itu ditambahkan. Skrip ini menambalnya.
 *
 * Aman dijalankan berulang: baris yang sudah ada tidak digandakan.
 *
 * Jalankan: php deploy/tambah-bahan-penolong.php [--dry-run]
 */

use App\Models\Formula;
use App\Models\FormulaMaterial;
use App\Models\Material;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$kering = in_array('--dry-run', $argv, true);

/** sku => [jumlah dalam satuan dasar, catatan] */
$penolong = [
    'KAWAT-LAS' => [60, 'Kawat las TIG'],
    'GAS-ARGON' => [150_000, 'Argon, sekitar 15 menit nyala'],
    'MATA-GERINDA' => [0.25, 'Satu mata untuk 4 knalpot'],
    'AMPLAS' => [2, ''],
    'COMPOUND' => [30, 'Compound poles'],
];

echo '== '.($kering ? 'SIMULASI' : 'TAMBAH').' BAHAN PENOLONG =='.PHP_EOL.PHP_EOL;

// Master bahannya dulu — seeder aman dijalankan ulang dan tidak menimpa apa pun.
if (! $kering) {
    Artisan::call('db:seed', ['--class' => 'ProduksiSeeder', '--force' => true]);
}

$kurang = collect($penolong)->keys()
    ->reject(fn ($sku) => Material::where('sku', $sku)->exists());

if ($kurang->isNotEmpty()) {
    echo '  Bahan belum ada di master: '.$kurang->implode(', ').PHP_EOL;
    echo '  Jalankan seeder dulu.'.PHP_EOL;
    exit(1);
}

$formula = Formula::with('materials')->where('code', 'M3-STD-RACING-V1')->first();

if (! $formula) {
    echo '  Formula contoh tidak ada — tidak ada yang perlu ditambal.'.PHP_EOL;
    exit(0);
}

$sudahAda = $formula->materials
    ->pluck('material_id')
    ->map(fn ($id) => Material::find($id)?->sku)
    ->filter()
    ->all();

$urutan = (int) $formula->materials->max('sort_order');
$ditambah = [];

foreach ($penolong as $sku => [$jumlah, $catatan]) {
    if (in_array($sku, $sudahAda, true)) {
        printf("  lewati  %-14s sudah ada di formula\n", $sku);

        continue;
    }

    $ditambah[$sku] = [$jumlah, $catatan, ++$urutan];
    $m = Material::where('sku', $sku)->first();
    printf("  tambah  %-14s %-28s %s\n", $sku, $m->name, $m->formatBase($jumlah));
}

if ($ditambah === []) {
    echo PHP_EOL.'Semua bahan penolong sudah ada di formula.'.PHP_EOL;
    exit(0);
}

if ($kering) {
    echo PHP_EOL.'Simulasi: '.count($ditambah).' baris akan ditambahkan.'.PHP_EOL;
    exit(0);
}

DB::transaction(function () use ($formula, $ditambah) {
    foreach ($ditambah as $sku => [$jumlah, $catatan, $urut]) {
        $formula->materials()->create([
            'material_id' => Material::where('sku', $sku)->value('id'),
            'bom_group' => FormulaMaterial::GRUP_PENOLONG,
            'input_mode' => 'direct',
            'piece_count' => 1,
            'qty' => $jumlah,
            'waste_percent' => 0,
            'use_nesting' => false,
            'notes' => $catatan ?: null,
            'sort_order' => $urut,
        ]);
    }
});

$formula = Formula::with(['materials.material', 'costs.component', 'machines.machine'])
    ->where('code', 'M3-STD-RACING-V1')->first();

$rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
$b = $formula->breakdown();

echo PHP_EOL.'== HASIL =='.PHP_EOL;
printf("  bahan baku      %s\n", $rp($formula->directMaterialCost()));
printf("  bahan penolong  %s\n", $rp($formula->consumableCost()));
printf("  tenaga kerja    %s\n", $rp($b['jasa']));
printf("  mesin           %s\n", $rp($b['mesin']));
printf("  overhead tetap  %s\n", $rp($b['overhead']));
printf("  HPP per %-8s %s\n", $formula->output_unit, $rp($b['per_unit']));
