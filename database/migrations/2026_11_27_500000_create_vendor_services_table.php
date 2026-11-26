<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master Jasa Vendor: vendor (supplier) + proses + tarif per satuan, mis.
 * "Bengkel Bending Jaya · Bending · Rp 3.000/buah". Komponen formula dan
 * formula setengah jadi memilih jasa dari sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('process', 100);
            $table->decimal('rate', 16, 2)->default(0);
            $table->string('unit', 30)->default('buah');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['supplier_id', 'process']);
        });

        Schema::table('formula_components', function (Blueprint $table) {
            $table->foreignId('vendor_service_id')->nullable()->after('work_mode')->constrained()->nullOnDelete();
        });

        Schema::table('formulas', function (Blueprint $table) {
            $table->foreignId('vendor_service_id')->nullable()->after('work_mode')->constrained()->nullOnDelete();
        });

        // Jasa yang sudah tertulis di formula dijadikan master.
        $now = now();
        $rows = DB::table('formula_components')->where('work_mode', 'vendor')->whereNotNull('vendor')->get(['id', 'vendor', 'process', 'service_cost'])
            ->map(fn ($row) => ['table' => 'formula_components'] + (array) $row)
            ->merge(DB::table('formulas')->where('work_mode', 'vendor')->whereNotNull('vendor')->get(['id', 'vendor', 'process', 'service_cost'])
                ->map(fn ($row) => ['table' => 'formulas'] + (array) $row));

        foreach ($rows as $row) {
            $supplierId = DB::table('suppliers')->whereRaw('LOWER(name) = ?', [mb_strtolower($row['vendor'])])->value('id')
                ?? DB::table('suppliers')->insertGetId(['name' => $row['vendor'], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            $process = trim((string) $row['process']) ?: 'Jasa';
            $serviceId = DB::table('vendor_services')->where('supplier_id', $supplierId)->where('process', $process)->value('id')
                ?? DB::table('vendor_services')->insertGetId([
                    'supplier_id' => $supplierId, 'process' => $process, 'rate' => $row['service_cost'], 'unit' => 'buah',
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            DB::table($row['table'])->where('id', $row['id'])->update(['vendor_service_id' => $serviceId]);
        }
    }

    public function down(): void
    {
        Schema::table('formulas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_service_id');
        });

        Schema::table('formula_components', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_service_id');
        });

        Schema::dropIfExists('vendor_services');
    }
};
