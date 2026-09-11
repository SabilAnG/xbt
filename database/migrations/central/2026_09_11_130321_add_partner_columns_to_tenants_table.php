<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data partner di tabel tenants bawaan tenancy.
 *
 * Tenancy menyimpan apa pun di kolom JSON `data`, tapi yang di bawah ini
 * dipakai untuk menyaring, mengurutkan, dan menghitung sisa masa — dan hal
 * seperti itu tidak pantas dicari di dalam JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('slug')->unique()->after('name');

            $table->string('owner_name')->after('slug');
            $table->string('owner_email')->after('owner_name');
            $table->string('owner_phone')->nullable()->after('owner_email');

            // Sandi pendaftaran, sudah ter-hash. Dipakai sekali untuk membuat
            // akun admin di database partner, lalu dikosongkan.
            $table->string('owner_password')->nullable()->after('owner_phone');

            $table->string('status')->default('menunggu')->after('owner_password');

            // Menu apa saja yang boleh dibuka partner ini.
            $table->json('features')->nullable()->after('status');

            $table->timestamp('expires_at')->nullable()->after('features');
            $table->timestamp('approved_at')->nullable()->after('expires_at');
            $table->text('notes')->nullable()->after('approved_at');

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn([
                'name', 'slug', 'owner_name', 'owner_email', 'owner_phone',
                'owner_password', 'status', 'features', 'expires_at',
                'approved_at', 'notes',
            ]);
        });
    }
};
