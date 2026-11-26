<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Master Satuan: daftar satuan beli & satuan pakai yang dipilih di Master Item. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $defaults = [
            ['Pcs', 'Buah / biji'], ['Set', 'Satu set lengkap'], ['Pasang', null], ['Lusin', '12 pcs'],
            ['Lembar', 'Plat, strimin'], ['Batang', 'Pipa (panjang diatur per item)'], ['Roll', 'Gaswool, kawat las'],
            ['Kg', 'Kilogram'], ['Gram', null], ['Liter', null], ['Tabung', 'Gas argon'], ['Dus', null],
            ['Meter', null], ['cm', 'Sentimeter'], ['cm²', 'Sentimeter persegi (luas plat)'],
        ];

        foreach ($defaults as $order => [$name, $description]) {
            DB::table('units')->insert([
                'name' => $name, 'description' => $description, 'sort_order' => $order + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
