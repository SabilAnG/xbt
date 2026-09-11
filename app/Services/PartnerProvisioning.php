<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use RuntimeException;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

/**
 * Menyiapkan toko seorang partner — satu-satunya tempat yang boleh melahirkan
 * database baru.
 *
 * Sengaja tidak digantung ke event `TenantCreated`: baris tenant lahir saat
 * orang mengisi formulir publik, dan formulir publik yang bisa membuat database
 * adalah cara termudah menghabiskan disk server. Database baru ada hanya
 * setelah seorang admin menekan tombol setuju.
 *
 * Urutannya penting: database dibuat, skema dijalankan, barulah akun admin
 * partner dibuat DI DALAM database itu. Salah urutan berarti akun mendarat di
 * database pusat, dan partner dapat kunci ke toko orang lain.
 */
class PartnerProvisioning
{
    /**
     * @param  array<int, string>  $fitur  kunci dari Tenant::FEATURES
     * @param  int|null  $hari  masa pakai; null berarti tanpa batas
     */
    public function setujui(Tenant $tenant, array $fitur, ?int $hari): void
    {
        if ($tenant->status === Tenant::AKTIF) {
            throw new RuntimeException('Partner ini sudah disetujui.');
        }

        if (blank($tenant->owner_password)) {
            throw new RuntimeException(
                'Pendaftaran ini tidak menyimpan sandi, jadi akun partnernya tidak bisa dibuat. Minta partner mendaftar ulang.'
            );
        }

        $sandi = $tenant->owner_password;

        // Di luar transaksi: MySQL tidak bisa membatalkan CREATE DATABASE, dan
        // membungkusnya dalam transaksi hanya memberi rasa aman yang palsu.
        app()->call([new CreateDatabase($tenant), 'handle']);
        (new MigrateDatabase($tenant))->handle();

        $tenant->run(function () use ($tenant, $sandi) {
            User::create([
                'name' => $tenant->owner_name,
                'email' => $tenant->owner_email,
                'password' => $sandi,   // sudah ter-hash sejak pendaftaran
                'email_verified_at' => now(),
            ]);
        });

        $tenant->forceFill([
            'status' => Tenant::AKTIF,
            'features' => array_values($fitur),
            'expires_at' => $hari ? now()->addDays($hari) : null,
            'approved_at' => now(),
            // Dipakai sekali, lalu tidak disimpan lagi. Sandi partner sejak ini
            // hanya ada di database partner sendiri.
            'owner_password' => null,
        ])->save();
    }

    /** Perpanjangan: masa pakai dihitung dari sekarang atau dari sisa yang ada. */
    public function perpanjang(Tenant $tenant, int $hari): void
    {
        $mulai = $tenant->kedaluwarsa() || $tenant->expires_at === null
            ? now()
            : $tenant->expires_at;

        $tenant->forceFill(['expires_at' => $mulai->copy()->addDays($hari)])->save();
    }

    /**
     * Menolak pendaftaran. Barisnya tetap disimpan supaya pendaftaran yang sama
     * tidak datang berulang tanpa jejak — yang dibuang hanya sandinya.
     */
    public function tolak(Tenant $tenant, ?string $alasan = null): void
    {
        $tenant->forceFill([
            'status' => Tenant::DITOLAK,
            'owner_password' => null,
            'notes' => $alasan,
        ])->save();
    }

    /**
     * Membuang toko partner berikut databasenya. Tidak bisa dibatalkan.
     */
    public function hapus(Tenant $tenant): void
    {
        $tenant->domains()->delete();

        // Databasenya dibuang oleh tenancy lewat event TenantDeleted. Sempat
        // dihapus manual di sini lebih dulu, dan job bawaan itu lalu menabrak
        // database yang sudah tidak ada — penghapusan gagal separuh jalan,
        // meninggalkan barisnya hidup tanpa toko.
        $tenant->delete();
    }
}
