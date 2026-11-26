<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Master Data Ongkos: nama ongkos formula + tarif bawaan per knalpot. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_options', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->decimal('amount', 16, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $order = 0;
        $used = DB::table('formula_costs')->selectRaw('name, AVG(amount) as amount')->groupBy('name')->pluck('amount', 'name');
        $names = collect(['Ongkos las', 'Ongkos bending', 'Finishing / poles', 'Packing'])->merge($used->keys())
            ->map(fn ($name) => trim((string) $name))->filter()->unique(fn ($name) => mb_strtolower($name));

        foreach ($names as $name) {
            DB::table('cost_options')->insert([
                'name' => $name, 'amount' => round((float) ($used[$name] ?? 0)), 'sort_order' => ++$order,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_options');
    }
};
