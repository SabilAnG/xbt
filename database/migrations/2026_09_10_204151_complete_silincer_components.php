<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Melengkapi komponen silincer sesuai daftar yang dipakai bengkel.
 *
 * Susunan awal tadi baru enam; kenyataannya ada tutup DB depan dan belakang,
 * moncong, tameng, sarangan, sampai baut dan mur yang memang ikut dihitung.
 *
 * Dua komponen lama diganti namanya, bukan ditambah: "Shock" ternyata shock
 * pipa DEPAN, dan "Tutup DB" adalah yang depan — begitu ada pasangan
 * belakangnya, nama tanpa keterangan jadi ambigu.
 */
return new class extends Migration
{
    /** Nama lama => nama baru. */
    private const GANTI_NAMA = [
        'Shock' => 'Shock Pipa Depan',
        'Tutup DB' => 'Tutup DB Depan',
    ];

    /** Urutannya mengikuti alur depan ke belakang, lalu pelengkap. */
    private const URUTAN = [
        'Shock Pipa Depan', 'Tutup DB Depan', 'Tabung Silincer', 'Tutup DB Belakang',
        'Moncong Knalpot', 'Shock Belakang', 'Sarangan', 'Tameng', 'Emblem',
        'Braket Atas', 'Braket Tengah', 'Braket Bawah', 'Braket Samping',
        'Baut', 'Mur', 'Baut Ripet',
    ];

    public function up(): void
    {
        $silincer = DB::table('exhaust_components')
            ->whereNull('parent_id')->where('name', 'Silincer')->first();

        if (! $silincer) {
            return;
        }

        foreach (self::GANTI_NAMA as $lama => $baru) {
            DB::table('exhaust_components')
                ->where('parent_id', $silincer->id)->where('name', $lama)
                ->update(['name' => $baru, 'updated_at' => now()]);
        }

        $adaNama = DB::table('exhaust_components')
            ->where('parent_id', $silincer->id)->pluck('name')->all();

        $urut = 0;

        foreach (self::URUTAN as $nama) {
            $urut += 10;

            if (in_array($nama, $adaNama, true)) {
                DB::table('exhaust_components')
                    ->where('parent_id', $silincer->id)->where('name', $nama)
                    ->update(['sort_order' => $urut, 'updated_at' => now()]);

                continue;
            }

            DB::table('exhaust_components')->insert([
                'parent_id' => $silincer->id,
                'name' => $nama,
                'sort_order' => $urut,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Sengaja tidak dibalik: yang ditambahkan di sini adalah data kerja,
        // dan menghapusnya kembali berisiko membuang yang sudah dipakai formula.
    }
};
