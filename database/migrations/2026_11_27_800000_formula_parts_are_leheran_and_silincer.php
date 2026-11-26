<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bagian formula knalpot hanya Leheran & Silincer. Aksesoris bukan bagian,
 * melainkan gudang/kategori bahan yang dipakai di dalam kedua bagian itu
 * (mis. Pureng di Leheran diambil dari Gudang Aksesoris).
 */
return new class extends Migration
{
    private const MOVE = [
        'Leheran' => ['Baut', 'Ring', 'Karet', 'Packing'],
        'Silincer' => ['Baut', 'Ring', 'Tameng', 'Stiker / emblem', 'Dus'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::MOVE as $part => $names) {
            $partId = DB::table('component_options')->where('kind', 'bagian')->where('name', $part)->value('id');
            if (! $partId) {
                continue;
            }

            $order = (int) DB::table('component_options')->where('parent_id', $partId)->max('sort_order');
            foreach ($names as $name) {
                if (! DB::table('component_options')->where('parent_id', $partId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
                    DB::table('component_options')->insert([
                        'kind' => 'komponen', 'parent_id' => $partId, 'name' => $name, 'sort_order' => ++$order,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        $accessories = DB::table('component_options')->where('kind', 'bagian')->where('name', 'Aksesoris')->value('id');

        if ($accessories) {
            // Masih dipakai formula lama → biarkan, tapi tidak lagi otomatis di formula baru.
            if (DB::table('formula_parts')->where('name', 'Aksesoris')->exists()) {
                DB::table('component_options')->where('id', $accessories)->update(['default_for' => null]);
            } else {
                DB::table('component_options')->where('parent_id', $accessories)->delete();
                DB::table('component_options')->where('id', $accessories)->delete();
            }
        }
    }

    public function down(): void
    {
        // Data saja; tidak dikembalikan.
    }
};
