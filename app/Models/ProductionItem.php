<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu barang di gudang produksi.
 *
 * Punya DUA satuan, dan itu inti dari seluruh modul:
 *
 *   satuan beli  (`unit`)      batang, lembar, kg, tabung, pcs — cara belanja
 *   satuan pakai (`baseUnit`)  mm, mm², gram, ml, pcs — cara formula memakainya
 *
 * `cost_price` adalah harga per satuan BELI. Harga per satuan pakai diturunkan
 * otomatis, jadi harga pipa naik cukup diubah di satu tempat dan seluruh
 * perhitungan ikut menyesuaikan.
 *
 * `stock` disimpan dalam satuan PAKAI supaya sisa potongan bisa dinyatakan:
 * "sisa 41,8 m" jauh lebih berguna daripada "6,97 batang".
 */
class ProductionItem extends Model
{
    /** Lebar material yang hilang jadi serbuk tiap kali memotong. */
    public const DEFAULT_KERF = 3.0;

    /**
     * Didapat dari mana. Menentukan apakah kekurangannya jadi daftar belanja
     * atau jadwal kerja — yang bisa dua-duanya menyerahkan pilihan itu ke
     * orang, bukan ke sistem.
     */
    public const SOURCES = [
        'beli' => 'Dibeli',
        'produksi' => 'Dibuat sendiri',
        'beli_produksi' => 'Dibeli atau dibuat sendiri',
    ];

    /**
     * Perannya di produk jadi, urut dari yang paling menentukan.
     *
     * "Aksesoris Utama" ada karena kenyataannya memang begitu: pegas dan karet
     * mounting bentuknya aksesoris, tapi tanpa keduanya knalpot tidak bisa
     * dipasang. Memaksanya masuk "Aksesoris" membuat daftar bahan wajib jadi
     * tidak lengkap.
     */
    public const ROLES = [
        'utama' => 'Bahan Utama',
        'aksesoris_utama' => 'Aksesoris Utama',
        'aksesoris' => 'Aksesoris',
        'penolong' => 'Bahan Penolong',
    ];

    /** Peran yang keberadaannya wajib untuk produk bisa jadi. */
    public const ROLES_WAJIB = ['utama', 'aksesoris_utama'];

    /**
     * Satuan yang dipakai saat mengetik ukuran, berikut nilainya dalam mm.
     *
     * Pipa disebut orang bengkel dalam inch, plat dalam mm, panjang batang
     * dalam meter. Ukurannya tetap DISIMPAN dalam mm — ini hanya mengatur cara
     * mengetiknya, supaya tidak ada perhitungan yang perlu tahu soal ini.
     */
    public const SIZE_UNITS = [
        'mm' => 1.0,
        'cm' => 10.0,
        'm' => 1000.0,
        'inch' => 25.4,
    ];

    public const SIZE_UNIT_LABELS = [
        'mm' => 'Milimeter (mm)',
        'cm' => 'Sentimeter (cm)',
        'm' => 'Meter (m)',
        'inch' => 'Inch (")',
    ];

    /** Bentuknya menentukan ukuran mana yang berlaku dan bagaimana dikonversi. */
    public const SHAPES = [
        'linear' => 'Batangan (pipa, as, strip)',
        'sheet' => 'Lembaran (plat)',
        'weight' => 'Berat (curah)',
        'volume' => 'Volume (gas, cairan)',
        'count' => 'Satuan (pcs, set)',
    ];

    /**
     * Bahan bakunya. Stainless dan besi adalah dua dunia yang berbeda di
     * bengkel knalpot: harganya berlipat, las-nya beda, dan pemesan biasanya
     * menyebut bahan lebih dulu daripada ukuran.
     *
     * Grade stainless dipisah karena SS201 dan SS304 berbeda harga cukup jauh
     * untuk mengubah HPP, dan keduanya sama-sama disebut "stainless" di pasar.
     * Yang belum diketahui grade-nya punya pilihannya sendiri, supaya tidak ada
     * yang menebak demi mengisi isian.
     */
    public const MATERIALS = [
        'ss201' => 'Stainless SS201',
        'ss304' => 'Stainless SS304',
        'stainless' => 'Stainless (grade lain)',
        'besi' => 'Besi',
        'galvanis' => 'Besi galvanis',
        'aluminium' => 'Aluminium',
        'kuningan' => 'Kuningan',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = [
        'sku', 'name', 'production_item_category_id',
        'source', 'role', 'shape', 'material', 'unit', 'size_unit',
        'length_mm', 'width_mm', 'diameter_mm', 'thickness_mm', 'weight_gram', 'volume_ml',
        'cost_price', 'stock', 'min_stock', 'min_reusable',
        'notes', 'is_active',
    ];

    /**
     * Disamakan dengan bawaan kolomnya supaya objek yang baru dibuat sudah
     * punya nilai sebelum sempat dibaca ulang dari database. Tanpa ini
     * `wajib()` pada barang yang baru saja disimpan membaca role kosong.
     */
    protected $attributes = [
        'source' => 'beli',
        'role' => 'utama',
        'shape' => 'count',
        'unit' => 'pcs',
        'size_unit' => 'mm',
    ];

    protected function casts(): array
    {
        return [
            'length_mm' => 'decimal:3',
            'width_mm' => 'decimal:3',
            'diameter_mm' => 'decimal:2',
            'thickness_mm' => 'decimal:2',
            'weight_gram' => 'decimal:3',
            'volume_ml' => 'decimal:3',
            'cost_price' => 'decimal:2',
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'min_reusable' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductionItemCategory::class, 'production_item_category_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ProductionItemMovement::class)->orderByDesc('moved_at');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductionItemStock::class);
    }

    /**
     * Stok sesungguhnya menurut kartu stok — seluruh gudang.
     *
     * Kolom `stock` hanya cache; ini yang menentukan. Dipakai setiap kali
     * pembukuan dibatalkan, karena menghitung ulang tetap benar walau ada
     * dokumen lain yang dibukukan sesudahnya.
     */
    public function computedStock(): float
    {
        return (float) $this->movements()->sum('qty_in')
            - (float) $this->movements()->sum('qty_out');
    }

    /** Stok menurut kartu stok, dibatasi satu gudang. */
    public function computedStockIn(int $warehouseId): float
    {
        $baris = $this->movements()->where('warehouse_id', $warehouseId);

        return (float) (clone $baris)->sum('qty_in') - (float) (clone $baris)->sum('qty_out');
    }

    /** Stok tercatat di satu gudang, dari tabel rincian. */
    public function stockIn(int $warehouseId): float
    {
        return (float) $this->stocks()->where('warehouse_id', $warehouseId)->value('qty');
    }

    /**
     * Hitung ulang total dan rinciannya dari kartu stok.
     *
     * Keduanya diturunkan dari sumber yang sama, jadi tidak bisa berselisih.
     */
    public function recalculateStock(): void
    {
        $perGudang = $this->movements()
            ->selectRaw('warehouse_id, SUM(qty_in) - SUM(qty_out) AS saldo')
            ->groupBy('warehouse_id')
            ->pluck('saldo', 'warehouse_id');

        foreach ($perGudang as $gudangId => $saldo) {
            if ($gudangId === null) {
                continue;
            }

            ProductionItemStock::updateOrCreate(
                ['production_item_id' => $this->id, 'warehouse_id' => $gudangId],
                ['qty' => (float) $saldo],
            );
        }

        // Gudang yang mutasinya habis terhapus harus ikut dinolkan, bukan
        // ditinggal memegang angka lama.
        $this->stocks()->whereNotIn('warehouse_id', $perGudang->keys()->all())->update(['qty' => 0]);

        $this->forceFill(['stock' => $this->computedStock()])->save();
    }

    // ----------------------------------------------------- sumber dan peran

    /** Kekurangannya bisa ditutup dengan membeli. */
    public function bisaDibeli(): bool
    {
        return $this->source !== 'produksi';
    }

    /** Kekurangannya bisa ditutup dengan membuat sendiri. */
    public function bisaDiproduksi(): bool
    {
        return $this->source !== 'beli';
    }

    public function displaySource(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    public function displayRole(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /** Wajib ada supaya produk bisa jadi — bukan sekadar pelengkap. */
    public function wajib(): bool
    {
        return in_array($this->role, self::ROLES_WAJIB, true);
    }

    public function displayShape(): string
    {
        return self::SHAPES[$this->shape] ?? $this->shape;
    }

    /** Kosong berarti belum dicatat, dan itu ditulis apa adanya. */
    public function displayMaterial(): string
    {
        return self::MATERIALS[$this->material] ?? ($this->material ?: '—');
    }

    // -------------------------------------------------- satuan saat mengetik

    /** Angka yang diketik dalam satuan pilihan -> mm untuk disimpan. */
    public static function toMm(float $nilai, ?string $satuan): float
    {
        return $nilai * (self::SIZE_UNITS[$satuan ?? 'mm'] ?? 1.0);
    }

    /** mm yang tersimpan -> angka dalam satuan pilihan untuk ditampilkan. */
    public static function fromMm(float $mm, ?string $satuan): float
    {
        $faktor = self::SIZE_UNITS[$satuan ?? 'mm'] ?? 1.0;

        return $faktor > 0 ? $mm / $faktor : $mm;
    }

    /** Lambang singkatnya untuk imbuhan isian: mm, cm, m, ". */
    public function sizeUnitSuffix(): string
    {
        return match ($this->size_unit) {
            'inch' => '"',
            default => (string) ($this->size_unit ?: 'mm'),
        };
    }

    // -------------------------------------------------------- konversi satuan

    /** Satuan yang dipakai formula dan stok. */
    public function baseUnit(): string
    {
        return match ($this->shape) {
            'linear' => 'mm',
            'sheet' => 'mm²',
            'weight' => 'gram',
            'volume' => 'ml',
            default => $this->unit ?: 'pcs',
        };
    }

    /**
     * Berapa satuan pakai yang didapat dari SATU satuan beli.
     *
     *   1 batang pipa 6 m        -> 6.000 mm
     *   1 lembar plat 1200x2400  -> 2.880.000 mm²
     *   1 kg glass wool          -> 1.000 gram
     */
    public function basePerUnit(): float
    {
        $n = match ($this->shape) {
            'linear' => (float) $this->length_mm,
            'sheet' => $this->sheetArea(),
            'weight' => (float) $this->weight_gram,
            'volume' => (float) $this->volume_ml,
            default => 1.0,
        };

        // Barang berdimensi yang ukurannya belum diisi jangan sampai membuat
        // pembagian nol; perlakukan 1:1 sampai datanya dilengkapi.
        return $n > 0 ? $n : 1.0;
    }

    /** Harga per satuan pakai — Rp/mm, Rp/mm², Rp/gram, Rp/ml, Rp/pcs. */
    public function basePrice(): float
    {
        return (float) $this->cost_price / $this->basePerUnit();
    }

    /** Satuan beli -> satuan pakai. 7 batang -> 42.000 mm */
    public function toBase(float $purchaseQty): float
    {
        return $purchaseQty * $this->basePerUnit();
    }

    /** Satuan pakai -> satuan beli. */
    public function toPurchase(float $baseQty): float
    {
        return $baseQty / $this->basePerUnit();
    }

    public function sheetArea(): float
    {
        return (float) $this->length_mm * (float) $this->width_mm;
    }

    // ------------------------------------------------- cara orang menghitung

    /**
     * Satuan yang enak dipakai menyebut SISA, berikut nilainya dalam satuan
     * pakai. Null untuk bentuk yang tidak mengenal sisa — baut tidak punya
     * "setengah baut" — dan untuk lembaran, yang sisanya berupa potongan
     * berukuran, bukan satu angka.
     *
     * @return array{0: string, 1: float}|null
     */
    public function remainderUnit(): ?array
    {
        return match ($this->shape) {
            'linear' => ['m', 1000.0],
            'weight' => ['gram', 1.0],
            'volume' => ['ml', 1.0],
            default => null,
        };
    }

    /**
     * Hasil hitung fisik dari cara orang menghitungnya: berapa satuan beli
     * yang utuh, ditambah sisanya.
     *
     *   pipa  : 4 batang + sisa 3 m          -> 4 x 6.000 + 3.000 = 27.000 mm
     *   plat  : 3 lembar + potongan 1200x800 -> 3 x 2.880.000 + 960.000 mm²
     *   baut  : 40 pcs                       -> 40
     */
    public function fromCount(?float $utuh, ?float $sisa = null, ?float $sisaLebar = null): float
    {
        $total = (float) $utuh * $this->basePerUnit();

        if ($this->shape === 'sheet') {
            return $total + ((float) $sisa * (float) $sisaLebar);
        }

        if ($satuan = $this->remainderUnit()) {
            return $total + ((float) $sisa * $satuan[1]);
        }

        return $total;
    }

    /**
     * Kebalikannya, untuk membuka kembali nota yang sudah diisi.
     *
     * Lembaran tidak bisa dibalik: banyak pasangan panjang x lebar memberi luas
     * yang sama. Karena itu rinciannya disimpan apa adanya, dan ini hanya
     * dipakai sebagai dugaan awal bila rincian itu belum ada.
     *
     * @return array{utuh: float, sisa: float}
     */
    public function splitCount(float $base): array
    {
        $per = $this->basePerUnit();
        $utuh = $per > 0 ? floor($base / $per) : 0.0;
        $sisaBase = $base - ($utuh * $per);
        $faktor = $this->remainderUnit()[1] ?? 1.0;

        return [
            'utuh' => $utuh,
            'sisa' => $faktor > 0 ? round($sisaBase / $faktor, 3) : $sisaBase,
        ];
    }

    /** "4 batang + 3 m" — ringkasan cara menghitungnya, untuk dibaca ulang. */
    public function countLabel(?float $utuh, ?float $sisa = null, ?float $sisaLebar = null): string
    {
        $trim = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
        $bagian = [];

        if ((float) $utuh > 0) {
            $bagian[] = $trim($utuh).' '.($this->unit ?: 'pcs');
        }

        if ($this->shape === 'sheet' && (float) $sisa > 0 && (float) $sisaLebar > 0) {
            $bagian[] = 'potongan '.$trim($sisa).' x '.$trim($sisaLebar).' mm';
        } elseif (($satuan = $this->remainderUnit()) && (float) $sisa > 0) {
            $bagian[] = 'sisa '.$trim($sisa).' '.$satuan[0];
        }

        return $bagian === [] ? '—' : implode(' + ', $bagian);
    }

    // ------------------------------------------------------------- memotong

    public static function kerf(): float
    {
        return (float) Setting::get('produksi.kerf_mm', self::DEFAULT_KERF);
    }

    /**
     * Berapa potong sepanjang $panjang yang muat dalam satu batang, dan berapa
     * yang tersisa di ujungnya.
     *
     * Dari batang 6 m, potongan 800 mm hanya dapat 7 buah — bukan 7,5 — karena
     * tiap irisan memakan 3 mm. Ujung 382 mm-nya tetap terbeli.
     *
     * @return array{muat: int, terpakai_per_unit: float, sisa_per_unit: float, muat_utuh: bool}
     */
    public function barNesting(float $panjang, ?float $kerf = null): array
    {
        $kerf ??= self::kerf();
        $batang = (float) $this->length_mm;

        if ($panjang <= 0 || $batang <= 0 || $panjang > $batang) {
            return ['muat' => 0, 'terpakai_per_unit' => 0.0, 'sisa_per_unit' => 0.0, 'muat_utuh' => false];
        }

        // Potongan terakhir tidak butuh kerf di belakangnya.
        $muat = (int) floor(($batang + $kerf) / ($panjang + $kerf));
        $terpakai = ($muat * $panjang) + (($muat - 1) * $kerf);

        return [
            'muat' => $muat,
            'terpakai_per_unit' => $terpakai,
            'sisa_per_unit' => max(0.0, $batang - $terpakai),
            'muat_utuh' => $muat > 0,
        ];
    }

    /**
     * Berapa potongan p x l yang muat dalam satu lembar, dan berapa yang
     * terbuang.
     *
     * Memakai pola potong lurus: semua potongan sejajar dalam satu orientasi,
     * karena itulah yang bisa dikerjakan gunting plat biasa. Kedua orientasi
     * dicoba, yang muat lebih banyak dipakai.
     *
     * @return array{muat: int, baris: int, kolom: int, diputar: bool, area_per_potong: float, sisa_persen: float, muat_utuh: bool}
     */
    public function sheetNesting(float $panjang, float $lebar, ?float $kerf = null, ?float $areaTerpakai = null): array
    {
        $kerf ??= self::kerf();
        $sl = (float) $this->length_mm;
        $sw = (float) $this->width_mm;

        $hitung = function (float $p, float $l) use ($sl, $sw, $kerf): array {
            if ($p <= 0 || $l <= 0) {
                return [0, 0, 0];
            }

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
        // kotak pembungkusnya — sudutnya ikut terbuang.
        $luasPotong = $areaTerpakai ?? ($panjang * $lebar);

        return [
            'muat' => $muat,
            'baris' => $diputar ? $barisP : $barisN,
            'kolom' => $diputar ? $kolomP : $kolomN,
            'diputar' => $diputar,
            'area_per_potong' => $muat > 0 ? $luasLembar / $muat : $luasPotong,
            'sisa_persen' => $muat > 0 && $luasLembar > 0
                ? (1 - ($muat * $luasPotong) / $luasLembar) * 100
                : 0.0,
            'muat_utuh' => $muat > 0,
        ];
    }

    /**
     * Apakah sisa sebesar ini masih layak dipakai lagi.
     *
     * Batas nol berarti bengkel belum memutuskan; perlakukan semua sisa
     * sebagai masih terpakai daripada diam-diam menyebutnya sampah.
     */
    public function isReusable(float $sisa): bool
    {
        $batas = (float) $this->min_reusable;

        return $batas <= 0 ? $sisa > 0 : $sisa >= $batas;
    }

    // --------------------------------------------------------------- tampilan

    /**
     * Angka satuan pakai dalam bentuk yang enak dibaca:
     * 41.835 mm -> "41,84 m" · 2.880.000 mm² -> "2,88 m²" · 1.500 gram -> "1,5 kg"
     */
    public function formatBase(float $baseQty): string
    {
        // Nol di belakang hanya dibuang bila memang ada komanya. Tanpa
        // penjagaan ini "6.720 mm²" terbaca jadi "6,72".
        $trim = function (float $n, int $d = 2): string {
            $teks = number_format($n, $d, ',', '.');

            return str_contains($teks, ',') ? rtrim(rtrim($teks, '0'), ',') : $teks;
        };

        return match ($this->shape) {
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

    /** "Rp 25/mm" — supaya turunan harganya terlihat. */
    public function displayBasePrice(): string
    {
        $p = $this->basePrice();
        $d = $p < 100 ? 2 : 0;

        return 'Rp '.number_format($p, $d, ',', '.').'/'.$this->baseUnit();
    }

    /** Ringkasan konversi, mis. "1 batang = 6 m". */
    public function conversionLabel(): string
    {
        if ($this->shape === 'count') {
            return '1 '.($this->unit ?: 'pcs');
        }

        return '1 '.($this->unit ?: 'unit').' = '.$this->formatBase($this->basePerUnit());
    }

    /**
     * Ringkasan ukuran fisiknya, ditulis kembali dalam satuan yang dipakai
     * mengetiknya — orang yang memasukkan 1,5" tidak mengenali "Ø38,1".
     *
     * Mis. "Ø28, 6.000 mm, tebal 1,2 mm" atau "Ø1,5", 236,22"".
     */
    public function displayDimensions(): string
    {
        $satuan = $this->size_unit ?: 'mm';
        $lambang = $this->sizeUnitSuffix();

        $trim = fn ($mm) => rtrim(rtrim(
            number_format(self::fromMm((float) $mm, $satuan), 2, ',', '.'), '0'
        ), ',');

        $bagian = [];

        if ((float) $this->diameter_mm > 0) {
            $bagian[] = 'Ø'.$trim($this->diameter_mm).$lambang;
        }

        if ((float) $this->length_mm > 0 && (float) $this->width_mm > 0) {
            $bagian[] = $trim($this->length_mm).' x '.$trim($this->width_mm).' '.$lambang;
        } elseif ((float) $this->length_mm > 0) {
            $bagian[] = $trim($this->length_mm).' '.$lambang;
        }

        if ((float) $this->thickness_mm > 0) {
            $bagian[] = 'tebal '.$trim($this->thickness_mm).' '.$lambang;
        }

        return $bagian === [] ? '—' : implode(', ', $bagian);
    }

    // ------------------------------------------------------------------ stok

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }

    public function stockValue(): float
    {
        return (float) $this->stock * $this->basePrice();
    }
}
