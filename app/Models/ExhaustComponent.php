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
 * P1 dan Tabung Silincer anaknya. Yang punya induk itulah yang benar-benar
 * dibuat dari bahan; induknya hanya wadah.
 */
class ExhaustComponent extends Model
{
    protected $fillable = [
        'parent_id', 'name', 'code', 'production_item_id',
        'notes', 'is_active', 'sort_order',
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

    /** Bahan baku pembentuknya. */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductionItem::class, 'production_item_id');
    }

    // ------------------------------------------------------------- tingkatan

    /** Bagian induk seperti Header dan Silincer — wadah, bukan barang. */
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

    /**
     * Bahan bakunya, atau keterangan kenapa tidak ada.
     *
     * Bagian induk memang tidak dibuat dari apa pun; membedakannya dari
     * komponen yang bahannya belum diisi itu penting, karena yang kedua
     * berarti ada pekerjaan yang belum selesai.
     */
    public function displayMaterial(): string
    {
        if ($this->isBagian()) {
            return '—';
        }

        return $this->item?->name ?? 'Belum dipilih';
    }

    /** Komponen yang bahannya belum ditentukan. */
    public function scopeTanpaBahan(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id')->whereNull('production_item_id');
    }
}
