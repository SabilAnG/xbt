<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Hasil audit keamanan:
 * - Verifikasi 2 langkah (kode authenticator) untuk akun admin.
 * - Kode tracking acak per pesanan, supaya status pengiriman tidak bisa
 *   diintip dengan menebak nomor nota yang berurutan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_code', 20)->nullable()->unique()->after('order_number');
        });

        DB::table('orders')->whereNull('tracking_code')->orderBy('id')->pluck('id')->each(function (int $id) {
            DB::table('orders')->where('id', $id)->update(['tracking_code' => 'HST'.Str::upper(Str::random(13))]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['tracking_code']);
            $table->dropColumn('tracking_code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
