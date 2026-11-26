<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Type motor punya brand induk (mis. Mio → Yamaha, Beat → Honda). */
return new class extends Migration
{
    private const KNOWN = [
        'Yamaha' => ['Mio', 'Aerox', 'Lexi', 'Fino', 'NMAX', 'XMAX', 'Jupiter', 'Vixion', 'R15'],
        'Honda' => ['Beat', 'Vario', 'Scoopy', 'PCX', 'ADV', 'Supra', 'CBR', 'CRF'],
        'Suzuki' => ['Satria', 'Nex', 'Address', 'GSX'],
        'Kawasaki' => ['Ninja', 'KLX', 'W175'],
        'Harley-Davidson' => ['Dyna', 'Softail', 'Sportster', 'Touring'],
    ];

    public function up(): void
    {
        Schema::table('motor_options', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('kind')->constrained('motor_options')->nullOnDelete();
        });

        foreach (self::KNOWN as $brand => $types) {
            $brandId = DB::table('motor_options')->where('kind', 'brand')->where('name', $brand)->value('id');

            if ($brandId) {
                DB::table('motor_options')->where('kind', 'type')->whereIn('name', $types)->update(['parent_id' => $brandId]);
            }
        }

        // Type yang sudah dipakai formula ber-brand ikut brand formula itu.
        DB::table('formulas')->whereNotNull('motor_brand')->whereNotNull('motor_type')->get(['motor_brand', 'motor_type'])
            ->each(function ($row) {
                $brandId = DB::table('motor_options')->where('kind', 'brand')->where('name', $row->motor_brand)->value('id');
                DB::table('motor_options')->where('kind', 'type')->where('name', $row->motor_type)->whereNull('parent_id')->update(['parent_id' => $brandId]);
            });
    }

    public function down(): void
    {
        Schema::table('motor_options', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
