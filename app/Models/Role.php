<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Peran pengguna: daftar fitur (menu) yang boleh dibuka. */
class Role extends Model
{
    use RecordsActivity;

    protected $fillable = ['key', 'name', 'description', 'all_access', 'is_system', 'permissions'];

    protected function casts(): array
    {
        return ['all_access' => 'boolean', 'is_system' => 'boolean', 'permissions' => 'array'];
    }

    public function activityLabel(): string
    {
        return 'Peran '.$this->name;
    }

    /** @return list<string> */
    public function activityFields(): array
    {
        return ['name', 'all_access', 'permissions'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'key');
    }

    public function isOwner(): bool
    {
        return $this->key === User::OWNER;
    }

    /** Boleh membuka fitur ini? Kelola pengguna hanya untuk Pemilik. */
    public function allows(string $feature): bool
    {
        if ($feature === 'users') {
            return $this->isOwner();
        }

        return $this->all_access || in_array($feature, $this->permissions ?? [], true);
    }
}
