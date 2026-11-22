<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master Komponen (bagian knalpot yang dibuat dari bahan baku) dan tiga jenis
 * gudang: bahan baku, setengah jadi (komponen), dan finish good (knalpot jadi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('group', 30)->default('lainnya');
            $table->string('unit', 30)->default('Pcs');
            $table->decimal('stock', 14, 3)->default(0);
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('component_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 14, 4);
            $table->timestamps();
            $table->unique(['component_id', 'material_id']);
        });

        Schema::create('component_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->decimal('qty_in', 14, 3)->default(0);
            $table->decimal('qty_out', 14, 3)->default(0);
            $table->decimal('balance_after', 14, 3)->default(0);
            $table->boolean('consumes_materials')->default(false);
            $table->date('moved_at');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['component_id', 'moved_at']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('type', 20)->nullable()->after('code');
        });

        $defaults = [
            ['bahan_baku', 'Gudang Bahan Baku', 'GD-BB', 'Pipa, plat, baut, gaswool, packing: bahan yang dibeli.'],
            ['setengah_jadi', 'Gudang Setengah Jadi', 'GD-WIP', 'Komponen hasil potong, bending, dan las yang menunggu dirakit.'],
            ['finish_good', 'Gudang Finish Good', 'GD-FG', 'Knalpot jadi yang siap dijual.'],
        ];

        foreach ($defaults as $i => [$type, $name, $code, $description]) {
            if (DB::table('warehouses')->where('code', $code)->exists()) {
                DB::table('warehouses')->where('code', $code)->update(['type' => $type]);

                continue;
            }

            DB::table('warehouses')->insert([
                'name' => $name, 'code' => $code, 'type' => $type, 'description' => $description,
                'is_active' => true, 'sort_order' => $i + 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('warehouses')->whereIn('code', ['GD-BB', 'GD-WIP', 'GD-FG'])->delete();

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::dropIfExists('component_movements');
        Schema::dropIfExists('component_materials');
        Schema::dropIfExists('components');
    }
};
