<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master data motor untuk formula: kategori, brand, type motor, dan varian
 * knalpot. Formula tetap menyimpan namanya (teks), master ini jadi daftar
 * pilihan yang bisa ditambah langsung dari form.
 */
return new class extends Migration
{
    private const CATEGORIES = ['matic' => 'Matic', 'bebek' => 'Bebek', 'sport' => 'Sport', 'lainnya' => 'Lainnya'];

    public function up(): void
    {
        Schema::create('motor_options', function (Blueprint $table) {
            $table->id();
            // kategori | brand | type | varian
            $table->string('kind', 20);
            $table->string('name', 150);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['kind', 'name']);
        });

        Schema::table('formulas', function (Blueprint $table) {
            $table->string('motor_brand', 100)->nullable()->after('motor_category');
        });

        // Kategori lama disimpan sebagai kunci (matic) → kini nama (Matic).
        foreach (self::CATEGORIES as $key => $label) {
            DB::table('formulas')->where('motor_category', $key)->update(['motor_category' => $label]);
        }

        $now = now();
        $seed = [
            'kategori' => array_values(self::CATEGORIES),
            'brand' => ['Honda', 'Yamaha', 'Suzuki', 'Kawasaki', 'Vespa', 'Harley-Davidson'],
            'type' => ['Mio', 'Beat', 'Vario', 'Scoopy', 'NMAX', 'PCX', 'Aerox', 'Lexi', 'Fino'],
            'varian' => [],
        ];
        $seed['kategori'] = array_merge($seed['kategori'], DB::table('formulas')->whereNotNull('motor_category')->distinct()->pluck('motor_category')->all());
        $seed['type'] = array_merge($seed['type'], DB::table('formulas')->whereNotNull('motor_type')->distinct()->pluck('motor_type')->all());
        $seed['varian'] = DB::table('formulas')->whereNotNull('variant')->distinct()->pluck('variant')->all();

        foreach ($seed as $kind => $names) {
            $order = 0;
            foreach (collect($names)->map(fn ($name) => trim((string) $name))->filter()->unique(fn ($name) => mb_strtolower($name)) as $name) {
                DB::table('motor_options')->insert(['kind' => $kind, 'name' => $name, 'sort_order' => ++$order, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::CATEGORIES as $key => $label) {
            DB::table('formulas')->where('motor_category', $label)->update(['motor_category' => $key]);
        }

        Schema::table('formulas', function (Blueprint $table) {
            $table->dropColumn('motor_brand');
        });

        Schema::dropIfExists('motor_options');
    }
};
