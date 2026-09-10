<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu komponen di dalam sebuah formula.
 *
 * Menjawab dua hal yang berbeda tiap motor: komponen ini dibuat dari bahan
 * apa, dan berapa ukurannya. Ukurannya diisi apa adanya — pipa cukup
 * panjangnya dalam cm — lalu kebutuhannya dalam satuan pakai diturunkan
 * otomatis, supaya tidak ada perkalian yang bisa salah di tangan orang.
 */
class FormulaLine extends Model
{
    /** Cara ukurannya disebut, mengikuti bentuk bahannya. */
    public const INPUT_MODES = [
        'length' => 'Panjang potongan',
        'rect' => 'Potongan persegi (p x l)',
        'count' => 'Jumlah satuan',
    ];

    protected $fillable = [
        'formula_id', 'exhaust_component_id', 'production_item_id',
        'input_mode', 'size_unit',
        'piece_length_mm', 'piece_width_mm', 'piece_count',
        'qty', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'piece_length_mm' => 'decimal:3',
            'piece_width_mm' => 'decimal:3',
            'piece_count' => 'decimal:3',
            'qty' => 'decimal:3',
        ];
    }

    protected $attributes = [
        'input_mode' => 'count',
        'size_unit' => 'cm',
        'piece_count' => 1,
    ];

    protected static function booted(): void
    {
        // Kebutuhan selalu diturunkan dari ukurannya, tidak pernah diketik —
        // dua angka yang bisa berselisih adalah dua angka yang tidak bisa
        // dipercaya.
        static::saving(fn (self $row) => $row->qty = $row->computeQty());
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(ExhaustComponent::class, 'exhaust_component_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductionItem::class, 'production_item_id');
    }

    // ----------------------------------------------------------- perhitungan

    /**
     * Kebutuhan dalam satuan pakai, dihitung dari bentuk potongannya.
     *
     * Kolomnya sudah menyimpan milimeter — `size_unit` hanya mengatur cara
     * mengetik dan menampilkannya. Mengonversinya lagi di sini pernah membuat
     * hasilnya sepuluh kali lipat.
     */
    public function computeQty(): float
    {
        $n = max((float) $this->piece_count, 0);
        $p = (float) $this->piece_length_mm;
        $l = (float) $this->piece_width_mm;

        return match ($this->input_mode) {
            'length' => $p * $n,
            'rect' => $p * $l * $n,
            default => $n,
        };
    }

    /** Biaya baris ini untuk satu kali resep. */
    public function subtotal(): float
    {
        return (float) $this->qty * ($this->item?->basePrice() ?? 0);
    }

    // --------------------------------------------------------------- tampilan

    /** "20 cm x 2" atau "324 x 320 cm" atau "4 pcs" */
    public function inputLabel(): string
    {
        $trim = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
        $satuan = $this->size_unit === 'inch' ? '"' : $this->size_unit;
        $n = (float) $this->piece_count;
        $kali = $n != 1.0 ? ' x '.$trim($n) : '';

        $panjang = ProductionItem::fromMm((float) $this->piece_length_mm, $this->size_unit);
        $lebar = ProductionItem::fromMm((float) $this->piece_width_mm, $this->size_unit);

        return match ($this->input_mode) {
            'length' => $trim($panjang).' '.$satuan.$kali,
            'rect' => $trim($panjang).' x '.$trim($lebar).' '.$satuan.$kali,
            default => $trim($n).' '.($this->item?->unit ?? 'pcs'),
        };
    }

    /** Kebutuhannya dalam satuan yang enak dibaca. */
    public function displayQty(): string
    {
        return $this->item?->formatBase((float) $this->qty) ?? (string) $this->qty;
    }

    /** Mode yang masuk akal untuk bentuk bahan tertentu. */
    public static function modesFor(?string $shape): array
    {
        return match ($shape) {
            'linear' => ['length' => 'Panjang potongan', 'count' => 'Jumlah satuan'],
            'sheet' => ['rect' => 'Potongan persegi (p x l)', 'count' => 'Jumlah satuan'],
            default => ['count' => 'Jumlah satuan'],
        };
    }
}
