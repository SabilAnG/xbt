<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris hitung fisik.
 *
 * Menyimpan dua hal sekaligus: CARA menghitungnya (4 batang + sisa 3 m) dan
 * HASILNYA dalam satuan pakai (27.000 mm). Yang pertama supaya notanya bisa
 * dibaca dan dibuka ulang seperti saat diisi; yang kedua karena itu yang
 * dipakai pembukuan.
 *
 * Keduanya tidak bisa berselisih: hasilnya selalu diturunkan dari caranya,
 * tidak pernah diketik terpisah.
 */
class ProductionItemOpnameItem extends Model
{
    protected $fillable = [
        'production_item_opname_id', 'production_item_id',
        'system_qty', 'count_whole', 'count_remainder', 'count_remainder_width',
        'physical_qty', 'difference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:3',
            'count_whole' => 'decimal:3',
            'count_remainder' => 'decimal:3',
            'count_remainder_width' => 'decimal:3',
            'physical_qty' => 'decimal:3',
            'difference' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $row) {
            // Hasil diturunkan dari cara menghitungnya, bukan diketik. Kalau
            // rinciannya memang tidak diisi, physical_qty apa adanya dipakai —
            // itu jalan keluar untuk barang yang dihitung langsung.
            if ($row->punyaRincian()) {
                $row->physical_qty = $row->item?->fromCount(
                    (float) $row->count_whole,
                    $row->count_remainder !== null ? (float) $row->count_remainder : null,
                    $row->count_remainder_width !== null ? (float) $row->count_remainder_width : null,
                ) ?? 0;
            }

            // Selisih selalu diturunkan agar tidak bisa bertentangan dengan
            // kedua angkanya.
            $row->difference = (float) $row->physical_qty - (float) $row->system_qty;
        });
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(ProductionItemOpname::class, 'production_item_opname_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductionItem::class, 'production_item_id');
    }

    /** Apakah baris ini diisi lewat cara menghitung, bukan angka langsung. */
    public function punyaRincian(): bool
    {
        return $this->count_whole !== null
            || $this->count_remainder !== null
            || $this->count_remainder_width !== null;
    }

    /** "4 batang + sisa 3 m" */
    public function countLabel(): string
    {
        return $this->item?->countLabel(
            $this->count_whole,
            $this->count_remainder,
            $this->count_remainder_width,
        ) ?? '—';
    }
}
