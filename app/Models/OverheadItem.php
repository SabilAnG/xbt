<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu pos biaya tetap bulanan.
 *
 * Overhead tidak dihitung sebagai persentase dari biaya bahan — cara itu
 * membuat knalpot mahal seolah menanggung sewa lebih besar daripada knalpot
 * murah, padahal keduanya memakai bengkel yang sama lamanya. Yang dipakai di
 * sini: total biaya tetap sebulan dibagi target produksi sebulan.
 */
class OverheadItem extends Model
{
    /** Dipakai bila target produksi bulanan belum diisi. */
    public const DEFAULT_TARGET = 50;

    protected $fillable = [
        'name', 'slug', 'monthly_cost', 'notes', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'monthly_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** Total biaya tetap sebulan. */
    public static function monthlyTotal(): float
    {
        return (float) static::query()->where('is_active', true)->sum('monthly_cost');
    }

    /** Berapa unit yang direncanakan selesai sebulan. */
    public static function targetPerMonth(): float
    {
        $n = (float) Setting::get('produksi.target_produksi_bulanan', self::DEFAULT_TARGET);

        // Target nol akan membuat pembagian meledak dan HPP jadi tak terhingga;
        // perlakukan sebagai "belum diisi".
        return $n > 0 ? $n : self::DEFAULT_TARGET;
    }

    /** Beban overhead yang ditanggung satu unit knalpot. */
    public static function perUnit(): float
    {
        return static::monthlyTotal() / static::targetPerMonth();
    }
}
