<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master Data Komponen: bagian formula (Leheran, Silincer, …) dan komponen di
 * tiap bagian (Pureng, P1, Tabung, …). Sebelumnya tertulis langsung di kode.
 */
return new class extends Migration
{
    private const SEED = [
        'Leheran' => ['knalpot', ['Pureng', 'Plendes', 'P1', 'P2', 'P3', 'P4', 'P5', 'Sok sambungan', 'Breket leher']],
        'Silincer' => ['knalpot', ['Tabung', 'Sarangan', 'Tutup depan', 'Tutup belakang', 'Moncong', 'Gaswool', 'Breket silincer', 'Tameng']],
        'Aksesoris' => ['knalpot', ['Baut', 'Ring', 'Stiker / emblem', 'Tameng', 'Karet', 'Packing', 'Dus']],
        'Bahan' => ['setengah_jadi', ['P1', 'P2', 'P3', 'P4', 'Plendes', 'Pureng']],
    ];

    public function up(): void
    {
        Schema::create('component_options', function (Blueprint $table) {
            $table->id();
            // bagian | komponen (komponen punya parent_id = bagiannya)
            $table->string('kind', 20);
            $table->foreignId('parent_id')->nullable()->constrained('component_options')->cascadeOnDelete();
            $table->string('name', 100);
            // Bagian otomatis di formula baru: knalpot | setengah_jadi
            $table->string('default_for', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['kind', 'parent_id', 'name']);
        });

        $now = now();
        $parts = collect(self::SEED)->map(fn ($row) => $row[1]);

        // Nama komponen yang sudah dipakai formula ikut masuk ke bagiannya.
        DB::table('formula_components')->join('formula_parts', 'formula_parts.id', '=', 'formula_components.formula_part_id')
            ->select('formula_parts.name as part', 'formula_components.name as component')->distinct()->get()
            ->each(function ($row) use (&$parts) {
                $parts[$row->part] = array_merge($parts[$row->part] ?? [], [$row->component]);
            });

        $order = 0;
        foreach ($parts as $part => $components) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $partId = DB::table('component_options')->insertGetId([
                'kind' => 'bagian', 'parent_id' => null, 'name' => $part, 'default_for' => self::SEED[$part][0] ?? null,
                'sort_order' => ++$order, 'created_at' => $now, 'updated_at' => $now,
            ]);

            $position = 0;
            foreach (collect($components)->map(fn ($name) => trim((string) $name))->filter()->unique(fn ($name) => mb_strtolower($name)) as $name) {
                DB::table('component_options')->insert([
                    'kind' => 'komponen', 'parent_id' => $partId, 'name' => $name, 'default_for' => null,
                    'sort_order' => ++$position, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('component_options');
    }
};
