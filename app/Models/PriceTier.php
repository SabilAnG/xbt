<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tingkatan harga jual.
 *
 * Harga dihitung dengan margin ATAS HARGA JUAL, bukan atas modal:
 *
 *     harga jual = HPP / (1 - margin - fee)
 *
 * Bedanya nyata. HPP Rp264.000 dengan margin 40%:
 *   - HPP x 1,4          = Rp369.600, laba Rp105.600 -> hanya 28,6% dari omzet
 *   - HPP / (1 - 0,40)   = Rp440.000, laba Rp176.000 -> benar 40% dari omzet
 *
 * Potongan marketplace ikut masuk penyebut supaya margin tidak diam-diam
 * termakan biaya admin.
 */
class PriceTier extends Model
{
    protected $fillable = [
        'name', 'slug', 'margin_percent', 'fee_percent', 'notes', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'margin_percent' => 'decimal:2',
            'fee_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** Pembulatan harga ke atas, mis. ke ribuan terdekat. */
    public static function rounding(): float
    {
        return (float) Setting::get('produksi.pembulatan_harga', 1000);
    }

    /** Bagian dari harga jual yang bukan modal: margin + potongan. */
    public function totalShare(): float
    {
        return ((float) $this->margin_percent + (float) $this->fee_percent) / 100;
    }

    /**
     * Harga jual sebelum dibulatkan.
     *
     * Margin 100% atau lebih tidak punya jawaban — penyebutnya nol atau
     * negatif — jadi dibatasi supaya tidak menghasilkan angka minus.
     */
    public function rawPrice(float $hpp): float
    {
        $sisa = 1 - $this->totalShare();

        return $sisa > 0.01 ? $hpp / $sisa : 0.0;
    }

    public function price(float $hpp): float
    {
        $harga = $this->rawPrice($hpp);
        $bulat = self::rounding();

        return $bulat > 0 ? ceil($harga / $bulat) * $bulat : $harga;
    }

    /**
     * Rincian satu tingkatan harga untuk sebuah HPP.
     *
     * @return array<string, float>
     */
    public function breakdown(float $hpp): array
    {
        $harga = $this->price($hpp);
        $potongan = $harga * ((float) $this->fee_percent / 100);
        $bersih = $harga - $potongan - $hpp;

        return [
            'harga' => $harga,
            'potongan' => $potongan,
            'diterima' => $harga - $potongan,
            'laba' => $bersih,
            // Margin nyata setelah pembulatan dan potongan — inilah yang
            // benar-benar masuk kantong, bukan angka yang diketik.
            'margin_nyata' => $harga > 0 ? ($bersih / $harga) * 100 : 0.0,
        ];
    }
}
