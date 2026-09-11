<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu banner iklan di situs.
 *
 * Posisinya ditentukan admin, bukan pemasang — yang membayar berhak tampil,
 * bukan berhak menentukan di mana halaman ini menaruhnya.
 */
class Advertisement extends Model
{
    /**
     * Tempat yang tersedia di halaman.
     *
     * Kuncinya dipakai langsung di Blade lewat `iklan('posisi')`, jadi
     * menambah tempat baru berarti menambah satu baris di sini dan satu
     * pemanggilan di view.
     */
    public const POSITIONS = [
        'header' => 'Bawah Header — melintang di atas halaman',
        'home_tengah' => 'Beranda Tengah — di antara bagian isi',
        'sidebar_produk' => 'Samping Daftar Produk',
        'footer' => 'Atas Footer — melintang sebelum footer',
    ];

    protected $fillable = [
        'title', 'position', 'image_path', 'target_url',
        'advertiser_name', 'advertiser_contact',
        'starts_at', 'ends_at', 'is_active', 'sort_order', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected $attributes = [
        'position' => 'footer',
        'is_active' => true,
        'sort_order' => 0,
        'clicks' => 0,
    ];

    /**
     * Iklan yang memang boleh tampil sekarang.
     *
     * Masa tayang menurunkannya sendiri. Iklan yang sudah lewat tapi masih
     * bertanda aktif adalah cara paling mudah menayangkan sesuatu yang sudah
     * tidak dibayar.
     */
    public function scopeTayang(Builder $query): Builder
    {
        $hariIni = now()->toDateString();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $hariIni))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $hariIni));
    }

    /** @return Collection<int, self> */
    public static function untuk(string $posisi): Collection
    {
        return static::query()
            ->tayang()
            ->where('position', $posisi)
            ->orderBy('sort_order')
            ->get();
    }

    public function displayPosition(): string
    {
        return self::POSITIONS[$this->position] ?? $this->position;
    }

    public function sedangTayang(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $hariIni = now()->startOfDay();

        return ! ($this->starts_at?->gt($hariIni) || $this->ends_at?->lt($hariIni));
    }

    /** "sampai 30 Sep 2026" atau "sudah lewat" — dibaca sekilas di daftar. */
    public function masaTayang(): string
    {
        if ($this->ends_at === null) {
            return 'tanpa batas';
        }

        return $this->ends_at->isPast()
            ? 'sudah lewat'
            : 'sampai '.$this->ends_at->translatedFormat('d M Y');
    }
}
