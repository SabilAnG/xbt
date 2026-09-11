<?php

namespace App\Filament\Auth;

use App\Models\Tenant;
use App\Services\SesiPartner;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;

/**
 * Satu pintu masuk untuk admin Hypersonic maupun partner.
 *
 * Emailnya yang menentukan. Kalau email itu milik seorang partner, database
 * dipindahkan ke miliknya lebih dulu — barulah sandi diperiksa, di tabel users
 * milik partner itu sendiri.
 *
 * Satu alamat untuk semua orang sengaja dipilih: partner cukup mengingat
 * alamat situsnya, bukan alamat lain yang hanya berlaku untuk dirinya. Dan
 * tidak ada satu pun langkah di server yang perlu dikerjakan tiap ada partner
 * baru.
 */
class MasukPanel extends Login
{
    public function authenticate(): ?LoginResponse
    {
        $partner = $this->cariPartner();

        if ($partner instanceof Tenant) {
            // Dipasang sebelum sandi diperiksa: yang memeriksanya harus membaca
            // tabel users milik partner, bukan milik pusat.
            tenancy()->initialize($partner);
        }

        $hasil = parent::authenticate();

        // Gagal masuk tidak boleh meninggalkan koneksi menempel di database
        // partner — permintaan berikutnya di halaman yang sama akan membacanya.
        if ($hasil === null) {
            tenancy()->end();

            return null;
        }

        SesiPartner::ingat($partner);

        return $hasil;
    }

    /**
     * Partner pemilik email yang sedang diisi, kalau ada.
     *
     * Hanya yang sudah disetujui. Pendaftaran yang masih menunggu belum punya
     * database untuk dimasuki.
     */
    private function cariPartner(): ?Tenant
    {
        $email = $this->form->getRawState()['email'] ?? null;

        if (blank($email)) {
            return null;
        }

        return Tenant::query()
            ->where('owner_email', $email)
            ->where('status', Tenant::AKTIF)
            ->first();
    }
}
