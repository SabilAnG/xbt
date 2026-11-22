<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Host live (data host di sheet Daftar). */
class Host extends Model
{
    protected $fillable = ['name', 'hourly_rate', 'account', 'is_active'];

    protected function casts(): array
    {
        return ['hourly_rate' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(HostPayroll::class);
    }
}
