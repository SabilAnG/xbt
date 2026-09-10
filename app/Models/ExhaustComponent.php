<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu bagian penyusun knalpot.
 *
 * Bertingkat lewat dirinya sendiri: Header dan Silincer adalah bagian induk,
 * P1 dan Tabung Silincer anaknya.
 *
 * Ini murni daftar — apa saja bagian yang menyusun sebuah knalpot. Bahan apa
 * yang dipakai, berapa banyak, dan berukuran berapa sengaja tidak ada di sini:
 * semuanya berbeda tiap model motor dan menjadi isi formula. Menaruhnya di
 * sini berarti menggandakan daftar ini untuk tiap model.
 */
class ExhaustComponent extends Model
{
    protected $fillable = [
        'parent_id', 'name', 'code', 'notes', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected $attributes = ['is_active' => true];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    // ------------------------------------------------------------- tingkatan

    /** Bagian induk seperti Header dan Silincer — wadah, bukan komponen. */
    public function isBagian(): bool
    {
        return $this->parent_id === null;
    }

    public function scopeBagian(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeKomponen(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    /** "Header / P1" — dipakai di tempat yang butuh nama tak ambigu. */
    public function fullName(): string
    {
        return $this->parent
            ? $this->parent->name.' / '.$this->name
            : $this->name;
    }
}
