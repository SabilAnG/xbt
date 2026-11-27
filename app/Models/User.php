<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Totp;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
        return ['role', 'is_active', 'email', 'two_factor_confirmed_at'];
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

    /** Verifikasi 2 langkah sudah aktif (kode authenticator diminta saat login). */
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /**
     * Kode 6 digit dari authenticator, sekali pakai: kode yang sama tidak bisa
     * dipakai ulang dalam jendela waktunya (mis. diintip lalu diketik orang lain).
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        $step = $this->two_factor_secret ? Totp::match($this->two_factor_secret, $code) : null;

        return $step !== null && Cache::add('2fa-used:'.$this->id.':'.$step, true, now()->addMinutes(3));
    }

    /** Pakai satu kode cadangan (hilang HP); kode itu langsung hangus. */
    public function useRecoveryCode(string $code): bool
    {
        $code = strtoupper(trim($code));
        $codes = $this->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $stored) {
            if (hash_equals($stored, $code)) {
                unset($codes[$index]);
                $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function newRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)))->all();
    }

    /** Boleh membuka fitur (menu) ini? Lihat AdminNavigation::featureOf(). */
    public function canAccess(string $feature): bool
    {
        return (bool) $this->roleRecord?->allows($feature);
    }

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

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
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
