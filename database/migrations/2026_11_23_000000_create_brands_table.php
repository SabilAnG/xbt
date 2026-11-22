<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master merek produk (XBT, FD, dan merek lain berikutnya).
 *
 * Produk dan baris resep tetap menyimpan NAMA merek (items.brand,
 * formula_materials.brand) supaya perhitungan stok identik dengan Excel; tabel
 * ini menjadi daftar resmi merek, dan ganti nama merek ikut memperbarui data
 * yang memakainya. Baris resep boleh berisi beberapa merek: "FD,ABC".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 20)->nullable();
            $table->string('color', 20)->default('#ea580c');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Merek yang sudah dipakai: dari produk, baris resep, dan pilihan "merek" di Daftar.
        $names = collect()
            ->merge(DB::table('list_options')->where('list', 'merek')->orderBy('sort_order')->pluck('value'))
            ->merge(DB::table('items')->whereNotNull('brand')->where('brand', '!=', '')->distinct()->pluck('brand'))
            ->merge(DB::table('formula_materials')->whereNotNull('brand')->where('brand', '!=', '')->pluck('brand')
                ->flatMap(fn ($value) => explode(',', $value)))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values();

        foreach ($names as $order => $name) {
            DB::table('brands')->insert([
                'name' => $name,
                'code' => strtoupper($name),
                'sort_order' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('list_options')->where('list', 'merek')->delete();
    }

    public function down(): void
    {
        foreach (DB::table('brands')->orderBy('sort_order')->get() as $brand) {
            DB::table('list_options')->insertOrIgnore([
                'list' => 'merek', 'value' => $brand->name, 'sort_order' => $brand->sort_order,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('brands');
    }
};
