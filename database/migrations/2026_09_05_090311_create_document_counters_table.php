<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penomoran nota per (dokumen, periode).
     *
     * Menggantikan cara lama yang membaca nomor terakhir lalu menambah satu —
     * dua kasir yang menyimpan bersamaan bisa mendapat nomor yang sama.
     * Di sini nomor diambil lewat baris terkunci (SELECT ... FOR UPDATE),
     * jadi urutannya aman.
     */
    public function up(): void
    {
        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->string('document');   // purchases | sales | expenses | stock_opnames
            $table->string('period', 6);  // Ym, mis. 202609
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['document', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_counters');
    }
};
