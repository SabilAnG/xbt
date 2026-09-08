<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mesin produksi — las, gerinda, bending, roll, kompresor, poles.
     *
     * Biaya mesin per jam tidak diketik, tapi diturunkan dari tiga hal yang
     * memang Anda ketahui:
     *
     *   penyusutan  = harga mesin / (umur tahun x jam pakai per tahun)
     *   listrik     = daya kW x tarif per kWh
     *   maintenance = biaya per tahun / jam pakai per tahun
     *
     * Listrik sengaja dihitung DI SINI, bukan sebagai pos terpisah, supaya
     * tidak terhitung dua kali. Listrik non-mesin (lampu, kipas) masuk
     * overhead bulanan di tahap berikutnya.
     */
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();

            $table->decimal('purchase_price', 16, 2)->default(0);
            $table->unsignedInteger('economic_life_years')->default(5);
            $table->decimal('hours_per_year', 10, 2)->default(1000);

            $table->decimal('power_kw', 10, 3)->default(0);
            $table->decimal('maintenance_per_year', 16, 2)->default(0);

            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
