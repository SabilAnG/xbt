<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun panel admin. Tidak ada registrasi publik: akun dibuat pemilik di menu
 * Pengguna, lewat seeder, atau `php artisan admin:user`. Peran menentukan
 * grup menu yang boleh dibuka.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    /** Nilai bawaan kolom ikut terisi di model baru (pengguna lama = pemilik). */
    protected $attributes = ['role' => 'owner', 'is_active' => true];

    public const OWNER = 'owner';

    /** Peran → label dan grup menu yang boleh dibuka ('*' = semua). */
    public const ROLES = [
        'owner' => ['label' => 'Pemilik', 'groups' => ['*'], 'hint' => 'Semua menu + kelola pengguna'],
        'admin' => ['label' => 'Admin', 'groups' => ['*'], 'hint' => 'Semua menu kecuali kelola pengguna'],
        'gudang' => ['label' => 'Kepala Gudang', 'groups' => ['Ringkasan', 'Pembelian', 'Katalog', 'Gudang', 'Produksi', 'Pekerja'], 'hint' => 'Pembelian, gudang, produksi, pekerja'],
        'kasir' => ['label' => 'Kasir / Penjualan', 'groups' => ['Ringkasan', 'Penjualan', 'Keuangan', 'Website'], 'hint' => 'Penjualan, keuangan, website'],
    ];

    public function roleLabel(): string
    {
        return self::ROLES[$this->role]['label'] ?? $this->role;
    }

    public function isOwner(): bool
    {
        return $this->role === self::OWNER;
    }

    /** Boleh membuka grup menu ini? Grup "Pengguna" hanya untuk pemilik. */
    public function canOpenGroup(string $group): bool
    {
        if ($group === 'Pengguna') {
            return $this->isOwner();
        }

        $groups = self::ROLES[$this->role]['groups'] ?? [];

        return in_array('*', $groups, true) || in_array($group, $groups, true);
    }

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
