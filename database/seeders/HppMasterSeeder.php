<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Master pendukung Formula (HPP): komponen biaya, mesin, overhead bulanan,
 * tingkatan harga, dan angka pengaturan produksi. Diambil dari ProduksiSeeder
 * tanpa bahan & formula demonya, supaya tidak bentrok dengan data Excel.
 * Idempoten: baris yang sudah ada tidak ditimpa.
 */
class HppMasterSeeder extends Seeder
{
    public function run(): void
    {
        $produksi = new ProduksiSeeder;
        $produksi->setCommand($this->command);

        $produksi->komponenBiaya();
        $produksi->mesin();
        $produksi->overheadBulanan();
        $produksi->tingkatanHarga();
    }
}
