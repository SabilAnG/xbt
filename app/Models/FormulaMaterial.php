<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris bahan di dalam formula.
 *
 * Kebutuhan diisi apa adanya sesuai bentuk bahannya — pipa dalam mm, plat
 * dalam ukuran potongan, endcap cukup diameternya — lalu `qty` (satuan dasar)
 * diturunkan otomatis. Jadi Anda tidak perlu menghitung 324 x 320 = 103.680
 * sendiri, dan tidak ada peluang salah kali.
 */
class FormulaMaterial extends Model
{
    /** Bagian resep, mengikuti urutan kerja bengkel. */
    public const BOM_GROUPS = [
        'header' => 'Header',
        'silencer' => 'Silencer',
        'mounting' => 'Mounting',
        'finishing' => 'Finishing',
        // Bahan penolong tidak menempel di produk tapi tetap habis dipakai:
        // kawat las, gas, mata gerinda, amplas, compound poles. Dipisah karena
        // dalam format harga pokok produksi ia masuk overhead pabrik, bukan
        // bahan baku.
        'consumable' => 'Bahan Penolong',
        'other' => 'Lain-lain',
    ];

    /** Grup yang dilaporkan sebagai overhead pabrik, bukan bahan baku. */
    public const GRUP_PENOLONG = 'consumable';

    public const INPUT_MODES = [
        'length' => 'Panjang (mm)',
        'rect' => 'Potongan persegi (p x l)',
        'circle' => 'Lingkaran (Ø)',
        'direct' => 'Isi langsung (satuan dasar)',
    ];

    protected $fillable = [
        'formula_id', 'material_id', 'bom_group', 'input_mode',
        'piece_length_mm', 'piece_width_mm', 'piece_diameter_mm', 'piece_count',
        'qty', 'waste_percent', 'use_nesting', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'piece_length_mm' => 'decimal:3',
            'piece_width_mm' => 'decimal:3',
            'piece_diameter_mm' => 'decimal:3',
            'piece_count' => 'decimal:3',
            'qty' => 'decimal:3',
            'waste_percent' => 'decimal:2',
            'use_nesting' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // qty selalu diturunkan dari input dimensional, tidak pernah diketik —
        // supaya angka di layar dan angka yang dipakai hitungan tidak bisa
        // berbeda.
        static::saving(function (self $row) {
            if ($row->input_mode !== 'direct') {
                $row->qty = $row->computeQty();
            }
        });
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    // ------------------------------------------------------------ perhitungan

    /** Kebutuhan dalam satuan dasar, dihitung dari bentuk potongannya. */
    public function computeQty(): float
    {
        $n = max((float) $this->piece_count, 0);

        return match ($this->input_mode) {
            'length' => (float) $this->piece_length_mm * $n,
            'rect' => (float) $this->piece_length_mm * (float) $this->piece_width_mm * $n,
            'circle' => (M_PI * pow((float) $this->piece_diameter_mm / 2, 2)) * $n,
            default => (float) $this->qty,
        };
    }

    /**
     * Apakah baris ini dihitung dengan nesting.
     *
     * Hanya masuk akal untuk lembaran yang ukurannya diketahui dan potongannya
     * berbentuk — bukan untuk baris "isi langsung" yang memang sudah menyebut
     * luas apa adanya.
     */
    public function usesNesting(): bool
    {
        return (bool) $this->use_nesting
            && $this->material?->dimension_type === 'sheet'
            && in_array($this->input_mode, ['rect', 'circle'], true)
            && $this->material->sheetArea() > 0;
    }

    /** Ukuran kotak pembungkus potongan; lingkaran dipotong dari kotaknya. */
    public function pieceBox(): array
    {
        return $this->input_mode === 'circle'
            ? [(float) $this->piece_diameter_mm, (float) $this->piece_diameter_mm]
            : [(float) $this->piece_length_mm, (float) $this->piece_width_mm];
    }

    /**
     * Rincian nesting baris ini, atau null bila tidak memakai nesting.
     *
     * @return array<string, mixed>|null
     */
    public function nesting(): ?array
    {
        if (! $this->usesNesting()) {
            return null;
        }

        [$p, $l] = $this->pieceBox();
        $n = max((float) $this->piece_count, 1);

        // Luas nyata satu potong: untuk lingkaran ini lebih kecil dari kotak
        // pembungkusnya, sehingga persentase sisanya jujur.
        return $this->material->nesting($p, $l, null, $this->computeQty() / $n);
    }

    /**
     * Kebutuhan nyata termasuk susut.
     *
     * Untuk plat dengan nesting, yang dibebankan bukan luas potongannya
     * melainkan jatah lembaran per potongan — sisa lembaran yang tidak terpakai
     * tetap uang yang sudah dikeluarkan.
     */
    public function effectiveQty(): float
    {
        $qty = (float) $this->qty;

        if ($n = $this->nesting()) {
            $qty = $n['area_per_potong'] * max((float) $this->piece_count, 0);
        }

        return $qty * (1 + ((float) $this->waste_percent / 100));
    }

    /** Luas bersih potongannya saja, tanpa jatah sisa lembaran. */
    public function netQty(): float
    {
        return (float) $this->qty * (1 + ((float) $this->waste_percent / 100));
    }

    /** Berapa rupiah yang hilang jadi sisa potong lembaran. */
    public function nestingWasteCost(): float
    {
        if (! $this->nesting()) {
            return 0.0;
        }

        return ($this->effectiveQty() - $this->netQty()) * ($this->material?->basePrice() ?? 0);
    }

    public function subtotal(): float
    {
        return $this->effectiveQty() * ($this->material?->basePrice() ?? 0);
    }

    // --------------------------------------------------------------- tampilan

    /** Ringkasan ukuran yang diketik, mis. "324 x 320 mm x 1" atau "Ø110 x 2". */
    public function inputLabel(): string
    {
        $trim = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
        $n = (float) $this->piece_count;
        $kali = $n != 1 ? ' x '.$trim($n) : '';

        return match ($this->input_mode) {
            'length' => $trim($this->piece_length_mm).' mm'.$kali,
            'rect' => $trim($this->piece_length_mm).' x '.$trim($this->piece_width_mm).' mm'.$kali,
            'circle' => 'Ø'.$trim($this->piece_diameter_mm).' mm'.$kali,
            default => $trim($this->qty).' '.($this->material?->baseUnit() ?? ''),
        };
    }

    /** Kebutuhan setelah susut, dalam satuan yang enak dibaca. */
    public function displayEffective(): string
    {
        return $this->material?->formatBase($this->effectiveQty()) ?? (string) $this->effectiveQty();
    }

    /** Mode input yang masuk akal untuk tipe bahan tertentu. */
    public static function modesFor(?string $dimensionType): array
    {
        return match ($dimensionType) {
            'linear' => ['length' => 'Panjang (mm)', 'direct' => 'Isi langsung (mm)'],
            'sheet' => [
                'rect' => 'Potongan persegi (p x l)',
                'circle' => 'Lingkaran (Ø)',
                'direct' => 'Isi langsung (mm²)',
            ],
            default => ['direct' => 'Jumlah'],
        };
    }
}
