<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bahan baku produksi.
 *
 * Satu bahan punya DUA satuan:
 *   - satuan beli  (`unit`)      : batang, lembar, kg, tabung, pcs — cara Anda belanja
 *   - satuan dasar (`baseUnit()`): mm, mm², gram, ml, pcs — cara formula memakainya
 *
 * `cost_price` adalah harga per satuan BELI. Harga per satuan dasar diturunkan
 * otomatis, jadi Anda tidak perlu menghitung Rp/meter sendiri setiap harga naik.
 *
 * `stock` disimpan dalam satuan DASAR supaya sisa potongan bisa dinyatakan —
 * "sisa 41,8 m" jauh lebih berguna daripada "6,97 batang".
 */
class Material extends Model
{
    /** Lebar mata potong bawaan, dipakai bila belum diatur di pengaturan. */
    public const DEFAULT_KERF = 3.0;

    public const DIMENSION_TYPES = [
        'linear' => 'Linear (pipa, batangan)',
        'sheet' => 'Lembaran (plat)',
        'weight' => 'Berat (curah)',
        'volume' => 'Volume (gas, cairan)',
        'count' => 'Satuan (pcs, set)',
    ];

    protected $fillable = [
        'sku', 'name', 'material_category_id', 'dimension_type', 'unit',
        'length_mm', 'sheet_length_mm', 'sheet_width_mm', 'weight_gram', 'volume_ml',
        'diameter_mm', 'thickness_mm',
        'cost_price', 'stock', 'min_stock', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'length_mm' => 'decimal:3',
            'sheet_length_mm' => 'decimal:3',
            'sheet_width_mm' => 'decimal:3',
            'weight_gram' => 'decimal:3',
            'volume_ml' => 'decimal:3',
            'diameter_mm' => 'decimal:2',
            'thickness_mm' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    // ------------------------------------------------------------- relasi

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(MaterialMovement::class)->orderByDesc('moved_at');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MaterialStock::class);
    }

    public function formulaLines(): HasMany
    {
        return $this->hasMany(FormulaMaterial::class);
    }

    // -------------------------------------------------------- konversi satuan

    /** Satuan yang dipakai formula dan stok. */
    public function baseUnit(): string
    {
        return match ($this->dimension_type) {
            'linear' => 'mm',
            'sheet' => 'mm²',
            'weight' => 'gram',
            'volume' => 'ml',
            default => $this->unit ?: 'pcs',
        };
    }

    /**
     * Berapa satuan dasar yang didapat dari SATU satuan beli.
     *
     *   1 batang pipa 6 m        -> 6.000 mm
     *   1 lembar plat 1200x2400  -> 2.880.000 mm²
     *   1 kg glass wool          -> 1.000 gram
     *   1 tabung gas 10.000 L    -> 10.000.000 ml
     */
    public function baseQtyPerPurchaseUnit(): float
    {
        $n = match ($this->dimension_type) {
            'linear' => (float) $this->length_mm,
            'sheet' => (float) $this->sheet_length_mm * (float) $this->sheet_width_mm,
            'weight' => (float) $this->weight_gram,
            'volume' => (float) $this->volume_ml,
            default => 1.0,
        };

        // Bahan berdimensi yang belum diisi ukurannya jangan sampai membuat
        // pembagian nol; perlakukan sebagai 1:1 sampai datanya dilengkapi.
        return $n > 0 ? $n : 1.0;
    }

    /** Harga per satuan dasar — Rp/mm, Rp/mm², Rp/gram, Rp/ml, Rp/pcs. */
    public function basePrice(): float
    {
        return (float) $this->cost_price / $this->baseQtyPerPurchaseUnit();
    }

    /** Satuan beli -> satuan dasar. 7 batang -> 42.000 mm */
    public function toBase(float $purchaseQty): float
    {
        return $purchaseQty * $this->baseQtyPerPurchaseUnit();
    }

    /** Satuan dasar -> satuan beli. Dipakai daftar belanja. */
    public function toPurchase(float $baseQty): float
    {
        return $baseQty / $this->baseQtyPerPurchaseUnit();
    }

    /** Luas satu lembar, untuk perhitungan nesting nanti. */
    public function sheetArea(): float
    {
        return (float) $this->sheet_length_mm * (float) $this->sheet_width_mm;
    }

    // ------------------------------------------------------------- nesting

    /** Lebar material yang hilang jadi serbuk tiap kali memotong. */
    public static function kerf(): float
    {
        return (float) Setting::get('produksi.kerf_mm', self::DEFAULT_KERF);
    }

    /**
     * Berapa potongan p x l yang muat dalam satu lembar, dan berapa yang terbuang.
     *
     * Memakai pola potong lurus (guillotine): semua potongan sejajar, satu
     * orientasi, karena itulah yang bisa dikerjakan gunting plat dan mesin
     * potong biasa. Kedua orientasi dicoba, yang muat lebih banyak dipakai.
     *
     * Kerf ditambahkan ke tiap potongan — dua potongan 300 mm dari lembaran
     * 600 mm tidak akan jadi bila mata potongnya memakan 3 mm.
     *
     * @return array{muat: int, baris: int, kolom: int, diputar: bool, area_per_potong: float, sisa_persen: float, muat_utuh: bool}
     */
    public function nesting(float $panjang, float $lebar, ?float $kerf = null, ?float $areaTerpakai = null): array
    {
        $kerf ??= self::kerf();
        $sl = (float) $this->sheet_length_mm;
        $sw = (float) $this->sheet_width_mm;

        $hitung = function (float $p, float $l) use ($sl, $sw, $kerf): array {
            if ($p <= 0 || $l <= 0) {
                return [0, 0, 0];
            }

            // Potongan terakhir tidak butuh kerf di belakangnya: n potongan
            // memakan n*ukuran + (n-1)*kerf. Tanpa koreksi ini, lembaran yang
            // pas terbagi habis dihitung kehilangan satu potongan.
            $kolom = (int) floor(($sl + $kerf) / ($p + $kerf));
            $baris = (int) floor(($sw + $kerf) / ($l + $kerf));

            return [$kolom * $baris, $baris, $kolom];
        };

        [$normal, $barisN, $kolomN] = $hitung($panjang, $lebar);
        [$putar, $barisP, $kolomP] = $hitung($lebar, $panjang);

        $diputar = $putar > $normal;
        $muat = max($normal, $putar);

        $luasLembar = $this->sheetArea();

        // Untuk lingkaran, luas yang benar-benar terpakai lebih kecil dari
        // kotak pembungkusnya — sudut-sudutnya ikut terbuang.
        $luasPotong = $areaTerpakai ?? ($panjang * $lebar);

        return [
            'muat' => $muat,
            'baris' => $diputar ? $barisP : $barisN,
            'kolom' => $diputar ? $kolomP : $kolomN,
            'diputar' => $diputar,
            // Potongan yang lebih besar dari lembaran tidak bisa dinesting;
            // pakai luas apa adanya supaya HPP tidak jadi nol atau tak hingga.
            'area_per_potong' => $muat > 0 ? $luasLembar / $muat : $luasPotong,
            'sisa_persen' => $muat > 0 && $luasLembar > 0
                ? (1 - ($muat * $luasPotong) / $luasLembar) * 100
                : 0.0,
            'muat_utuh' => $muat > 0,
        ];
    }

    /** "1 lembar muat 24 potong (6 x 4), sisa 8%" */
    public function nestingLabel(float $panjang, float $lebar, ?float $kerf = null, ?float $areaTerpakai = null): string
    {
        $n = $this->nesting($panjang, $lebar, $kerf, $areaTerpakai);

        if (! $n['muat_utuh']) {
            return 'Potongan lebih besar dari lembaran — nesting tidak dipakai.';
        }

        return sprintf(
            '1 %s muat %d potong (%d x %d%s), sisa %s%%',
            $this->unit ?: 'lembar',
            $n['muat'], $n['kolom'], $n['baris'],
            $n['diputar'] ? ', diputar' : '',
            number_format($n['sisa_persen'], 1, ',', '.')
        );
    }

    // --------------------------------------------------------------- tampilan

    /**
     * Angka satuan dasar dalam bentuk yang enak dibaca:
     * 41.835 mm -> "41,84 m" · 2.880.000 mm² -> "2,88 m²" · 1.500 gram -> "1,5 kg"
     */
    public function formatBase(float $baseQty): string
    {
        // Nol di belakang hanya dibuang bila memang ada koma desimalnya.
        // Tanpa penjagaan ini "6.720 mm2" terbaca jadi "6,72" dan "0" hilang
        // sama sekali, karena titik di sini pemisah ribuan.
        $trim = function (float $n, int $d = 2): string {
            $teks = number_format($n, $d, ',', '.');

            return str_contains($teks, ',') ? rtrim(rtrim($teks, '0'), ',') : $teks;
        };

        return match ($this->dimension_type) {
            'linear' => abs($baseQty) >= 1000
                ? $trim($baseQty / 1000).' m'
                : $trim($baseQty, 1).' mm',
            'sheet' => abs($baseQty) >= 1_000_000
                ? $trim($baseQty / 1_000_000).' m²'
                : $trim($baseQty, 0).' mm²',
            'weight' => abs($baseQty) >= 1000
                ? $trim($baseQty / 1000, 3).' kg'
                : $trim($baseQty, 1).' gram',
            'volume' => abs($baseQty) >= 1000
                ? $trim($baseQty / 1000, 2).' L'
                : $trim($baseQty, 0).' ml',
            default => $trim($baseQty, 2).' '.($this->unit ?: 'pcs'),
        };
    }

    public function displayStock(): string
    {
        return $this->formatBase((float) $this->stock);
    }

    /** "Rp 25/mm" — dipakai di tabel dan form agar turunannya terlihat. */
    public function displayBasePrice(): string
    {
        $p = $this->basePrice();
        $d = $p < 100 ? 2 : 0;

        return 'Rp '.number_format($p, $d, ',', '.').'/'.$this->baseUnit();
    }

    /** Ringkasan konversi, mis. "1 batang = 6 m". */
    public function conversionLabel(): string
    {
        if ($this->dimension_type === 'count') {
            return '1 '.($this->unit ?: 'pcs');
        }

        return '1 '.($this->unit ?: 'unit').' = '.$this->formatBase($this->baseQtyPerPurchaseUnit());
    }

    // ------------------------------------------------------------------ stok

    public function computedStock(): float
    {
        return (float) $this->movements()->sum('qty_in') - (float) $this->movements()->sum('qty_out');
    }

    public function recalculateStock(): void
    {
        $this->forceFill(['stock' => $this->computedStock()])->save();
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }

    /** Nilai stok = stok (satuan dasar) x harga per satuan dasar. */
    public function stockValue(): float
    {
        return (float) $this->stock * $this->basePrice();
    }
}
