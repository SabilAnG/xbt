<?php

/**
 * Membersihkan data contoh modul produksi yang sudah tidak dipakai.
 *
 * Yang dihapus hanya baris yang:
 *   - memang bagian dari data demo lama (dicocokkan lewat SKU/slug/kode), DAN
 *   - tidak dirujuk formula lain, nota produksi, pembelian, mutasi, atau stok rak.
 *
 * Baris yang ternyata masih dipakai TIDAK dihapus dan dilaporkan alasannya.
 * Kalau ada satu saja yang masih dipakai secara tak terduga, seluruh
 * penghapusan dibatalkan — lebih baik tidak jadi daripada setengah jalan.
 *
 * Jalankan dengan --dry-run dulu untuk melihat apa yang akan terjadi:
 *   php deploy/hapus-demo-produksi.php --dry-run
 */

use App\Models\CostComponent;
use App\Models\Formula;
use App\Models\FormulaCost;
use App\Models\FormulaMachine;
use App\Models\FormulaMaterial;
use App\Models\Machine;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\MaterialStock;
use App\Models\OverheadItem;
use App\Models\PriceTier;
use App\Models\Production;
use App\Models\ProductionCost;
use App\Models\ProductionMaterial;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$kering = in_array('--dry-run', $argv, true);

/** Data demo lama, dicocokkan persis. */
$formulaDemo = ['KNALPOT-MIO-RACING'];
$bahanDemo = ['PIPA-SS-2', 'PIPA-SS-15', 'PLAT-12-LAMA', 'INLET-MIO', 'POLES-AUTOSOL'];
$biayaDemo = ['jasa-las', 'bubut', 'bending-pipa-lama', 'poles-finishing', 'tenaga-perakitan'];

$masalah = [];
$akanHapus = ['formula' => [], 'bahan' => [], 'biaya' => []];

echo '== '.($kering ? 'SIMULASI' : 'PENGHAPUSAN').' DATA DEMO PRODUKSI =='.PHP_EOL.PHP_EOL;

// ---------------------------------------------------------------- formula
foreach ($formulaDemo as $kode) {
    $f = Formula::where('code', $kode)->first();

    if (! $f) {
        continue;
    }

    $nota = Production::where('formula_id', $f->id)->count();

    if ($nota > 0) {
        $masalah[] = "Formula {$kode} dipakai {$nota} nota produksi.";

        continue;
    }

    $akanHapus['formula'][] = $f;
    printf("  formula %-24s %s (%d bahan, %d biaya)\n", $kode, $f->name,
        $f->materials()->count(), $f->costs()->count());
}

$idFormulaDihapus = collect($akanHapus['formula'])->pluck('id')->all();

// ----------------------------------------------------------------- bahan
foreach ($bahanDemo as $sku) {
    $m = Material::where('sku', $sku)->first();

    if (! $m) {
        continue;
    }

    $alasan = [];

    // Baris formula yang ikut terhapus tidak dihitung sebagai pemakai.
    $dipakaiFormula = FormulaMaterial::where('material_id', $m->id)
        ->whereNotIn('formula_id', $idFormulaDihapus)->count();

    if ($dipakaiFormula > 0) {
        $alasan[] = "{$dipakaiFormula} baris formula lain";
    }

    if (($n = ProductionMaterial::where('material_id', $m->id)->count()) > 0) {
        $alasan[] = "{$n} baris nota produksi";
    }

    if (($n = MaterialMovement::where('material_id', $m->id)->count()) > 0) {
        $alasan[] = "{$n} mutasi stok";
    }

    if ((float) $m->stock != 0.0) {
        $alasan[] = 'stok bukan nol ('.$m->stock.')';
    }

    if ($alasan !== []) {
        $masalah[] = "Bahan {$sku} ({$m->name}) masih dipakai: ".implode(', ', $alasan).'.';

        continue;
    }

    $akanHapus['bahan'][] = $m;
    printf("  bahan   %-24s %s\n", $sku, $m->name);
}

// ----------------------------------------------------------------- biaya
foreach ($biayaDemo as $slug) {
    $c = CostComponent::where('slug', $slug)->first();

    if (! $c) {
        continue;
    }

    $dipakaiFormula = FormulaCost::where('cost_component_id', $c->id)
        ->whereNotIn('formula_id', $idFormulaDihapus)->count();
    $dipakaiNota = ProductionCost::where('cost_component_id', $c->id)->count();

    if ($dipakaiFormula > 0 || $dipakaiNota > 0) {
        $masalah[] = "Komponen biaya {$slug} ({$c->name}) masih dipakai: "
            ."{$dipakaiFormula} baris formula lain, {$dipakaiNota} baris nota produksi.";

        continue;
    }

    $akanHapus['biaya'][] = $c;
    printf("  biaya   %-24s %s\n", $slug, $c->name);
}

// --------------------------------------------------------------- putusan
echo PHP_EOL;

if ($masalah !== []) {
    echo '== TIDAK JADI DIHAPUS =='.PHP_EOL;
    foreach ($masalah as $m) {
        echo '  '.$m.PHP_EOL;
    }
    echo PHP_EOL.'Ada yang masih dipakai. Tidak ada yang dihapus.'.PHP_EOL;
    exit(1);
}

$jumlah = count($akanHapus['formula']) + count($akanHapus['bahan']) + count($akanHapus['biaya']);

if ($jumlah === 0) {
    echo 'Tidak ada data demo yang tersisa. Tidak ada yang perlu dihapus.'.PHP_EOL;
    exit(0);
}

if ($kering) {
    echo "Simulasi: {$jumlah} baris akan dihapus. Jalankan tanpa --dry-run untuk benar-benar menghapus.".PHP_EOL;
    exit(0);
}

DB::transaction(function () use ($akanHapus) {
    foreach ($akanHapus['formula'] as $f) {
        // Baris anak dulu, baru induknya — foreign key-nya restrictOnDelete.
        FormulaMaterial::where('formula_id', $f->id)->delete();
        FormulaCost::where('formula_id', $f->id)->delete();
        FormulaMachine::where('formula_id', $f->id)->delete();
        $f->delete();
    }

    foreach ($akanHapus['bahan'] as $m) {
        MaterialStock::where('material_id', $m->id)->delete();
        $m->delete();
    }

    foreach ($akanHapus['biaya'] as $c) {
        $c->delete();
    }
});

echo "Terhapus: {$jumlah} baris.".PHP_EOL.PHP_EOL;

echo '== SISA DATA =='.PHP_EOL;
printf("  bahan %d · komponen biaya %d · formula %d · mesin %d · overhead %d · tingkatan harga %d\n",
    Material::count(), CostComponent::count(), Formula::count(),
    Machine::count(), OverheadItem::count(), PriceTier::count());

$f = Formula::with(['materials.material', 'costs.component', 'machines.machine'])
    ->where('code', 'M3-STD-RACING-V1')->first();

if ($f) {
    printf("  HPP formula contoh: Rp %s per %s\n",
        number_format($f->hppPerUnit(), 0, ',', '.'), $f->output_unit);
}
