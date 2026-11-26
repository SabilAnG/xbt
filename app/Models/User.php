<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    use HasFactory, Notifiable, RecordsActivity;

    public function activityLabel(): string
    {
        return 'Pengguna '.$this->name.' ('.$this->roleLabel().')';
    }

    /** @return list<string> */
    public function activityFields(): array
    {
        return ['role', 'is_active', 'email'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    /** Nilai bawaan kolom ikut terisi di model baru (pengguna lama = pemilik). */
    protected $attributes = ['role' => 'owner', 'is_active' => true];

    public const OWNER = 'owner';

    public function roleRecord(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'key');
    }

    public function roleLabel(): string
    {
        return $this->roleRecord?->name ?? $this->role;
    }

    public function isOwner(): bool
    {
        return $this->role === self::OWNER;
    }

    /** Boleh membuka fitur (menu) ini? Lihat AdminNavigation::featureOf(). */
    public function canAccess(string $feature): bool
    {
        return (bool) $this->roleRecord?->allows($feature);
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
