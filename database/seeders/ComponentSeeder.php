<?php

namespace Database\Seeders;

use App\Models\Component;
use Illuminate\Database\Seeder;

/**
 * Daftar awal komponen knalpot. Tidak menimpa komponen yang sudah ada; bahan
 * penyusunnya diisi sendiri oleh admin di Master Komponen.
 */
class ComponentSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            ['KMP-LHR', 'Leher / Header', 'leher', 'Set', 'Pipa leher hasil bending, dari mesin sampai silencer.'],
            ['KMP-PLD', 'Plandes Leher', 'leher', 'Pcs', 'Flange penghubung leher ke blok mesin.'],
            ['KMP-SOK', 'Pipa Sambungan (Sok)', 'leher', 'Pcs', 'Penyambung leher dengan silencer.'],
            ['KMP-TBG', 'Tabung Silencer', 'silencer', 'Pcs', 'Selongsong luar silencer dari plat stainless.'],
            ['KMP-SRG', 'Sarangan / Inner Silencer', 'silencer', 'Pcs', 'Pipa berlubang di dalam silencer, dibungkus gaswool.'],
            ['KMP-TTD', 'Tutup Silencer Depan', 'silencer', 'Pcs', 'Kerucut / tutup sisi masuk.'],
            ['KMP-TTB', 'Tutup Silencer Belakang', 'silencer', 'Pcs', 'Tutup sisi buang.'],
            ['KMP-MCG', 'Moncong / Ujung Knalpot', 'ujung', 'Pcs', 'Ujung buang (tip).'],
            ['KMP-BRK', 'Breket / Dudukan', 'dudukan', 'Pcs', 'Dudukan silencer ke rangka motor.'],
            ['KMP-TMG', 'Tameng / Cover Panas', 'aksesoris', 'Pcs', 'Pelindung panas di leher.'],
        ];

        foreach ($components as $i => [$code, $name, $group, $unit, $description]) {
            Component::firstOrCreate(['code' => $code], [
                'name' => $name, 'group' => $group, 'unit' => $unit, 'description' => $description,
                'is_active' => true, 'sort_order' => $i + 1,
            ]);
        }
    }
}
