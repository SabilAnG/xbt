<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Satu partner berikut tokonya sendiri.
 *
 * Datanya duduk di database terpisah — dibuat saat admin menyetujui, bukan saat
 * orang mendaftar. Pendaftaran yang belum disetujui tidak boleh menimbulkan
 * database baru di server: siapa pun bisa mengisi formulir publik, dan database
 * yang lahir dari formulir publik adalah pintu untuk menghabiskan disk.
 *
 * Sandi pendaftaran disimpan sudah ter-hash dan dipakai sekali — menjadi akun
 * admin di database partner saat disetujui, lalu kolomnya dikosongkan.
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public const MENUNGGU = 'menunggu';

    public const AKTIF = 'aktif';

    public const DITOLAK = 'ditolak';

    public const STATUSES = [
        self::MENUNGGU => 'Menunggu Persetujuan',
        self::AKTIF => 'Aktif',
        self::DITOLAK => 'Ditolak',
    ];

    /**
     * Hak yang bisa dicentang admin saat menyetujui.
     *
     * Partner yang hanya berjualan lewat halaman toko tidak perlu melihat menu
     * produksi, dan menu yang tidak pernah dipakai hanya membuat panel terasa
     * rumit. Kuncinya disimpan apa adanya di kolom `features`.
     */
    public const FEATURES = [
        'landing' => 'Halaman Toko',
        'toko' => 'Produk & Toko Online',
        'operasional' => 'Operasional (pembelian, penjualan, stok barang)',
        'produksi' => 'Produksi (bahan, formula, HPP)',
        'aset' => 'Aset (dompet, stok opname, laporan)',
        'master' => 'Master Data',
    ];

    /** Pilihan masa pakai saat menyetujui, dalam hari. */
    public const DURATIONS = [
        1 => '1 hari',
        7 => '1 minggu',
        30 => '1 bulan',
        90 => '3 bulan',
        365 => '1 tahun',
    ];

    /**
     * Kolom milik kami sendiri. Sisanya tetap masuk kolom `data` bawaan
     * tenancy, dan itu memang tempat yang benar untuk hal-hal yang tidak
     * pernah dicari lewat query.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'owner_name',
            'owner_email',
            'owner_phone',
            'owner_password',
            'status',
            'features',
            'expires_at',
            'approved_at',
            'notes',
        ];
    }

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('status', self::MENUNGGU);
    }

    // ------------------------------------------------------------- keadaan

    public function isAktif(): bool
    {
        return $this->status === self::AKTIF && ! $this->kedaluwarsa();
    }

    /**
     * Masa pakai habis. Panel dikunci, tapi datanya tetap utuh — memperpanjang
     * mengembalikan semuanya seperti semula.
     */
    public function kedaluwarsa(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function punyaHak(string $fitur): bool
    {
        return in_array($fitur, $this->features ?? [], true);
    }

    /** "3 hari lagi" atau "habis 2 hari lalu" — dibaca sekilas di daftar. */
    public function sisaMasa(): string
    {
        if ($this->expires_at === null) {
            return 'tanpa batas';
        }

        return $this->kedaluwarsa()
            ? 'habis '.$this->expires_at->diffForHumans()
            : $this->expires_at->diffForHumans();
    }

    public function displayStatus(): string
    {
        if ($this->status === self::AKTIF && $this->kedaluwarsa()) {
            return 'Kedaluwarsa';
        }

        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Alamat toko partner.
     *
     * Path, bukan subdomain: partner yang disetujui langsung hidup tanpa satu
     * pun langkah di server — tidak ada DNS yang perlu ditambah, tidak ada
     * vhost, tidak ada sertifikat.
     */
    public function alamat(): string
    {
        return rtrim(config('app.url'), '/').'/toko/'.$this->slug;
    }

    /** Tanpa skema, untuk ditampilkan ringkas di tabel. */
    public function alamatRingkas(): string
    {
        return preg_replace('#^https?://#', '', $this->alamat());
    }
}
