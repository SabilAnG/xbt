<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gudang produksi.
 *
 * Jenisnya adalah keterangan dan bawaan, BUKAN pagar. Sistem tidak menolak
 * pipa disimpan di Finish Good — bengkel sering menitipkan barang sementara,
 * dan memaksanya hanya membuat orang mengakali sistem. Yang diberikan
 * peringatan halus, bukan penolakan.
 */
class Warehouse extends Model
{
    public const TYPES = [
        'bahan_mentah' => 'Bahan Mentah',
        'setengah_jadi' => 'Barang Setengah Jadi',
        'finish_good' => 'Finish Good',
        'bahan_sisa' => 'Bahan Sisa',
    ];

    protected $fillable = ['code', 'name', 'type', 'description', 'is_active', 'sort_order'];

    protected $attributes = ['type' => 'bahan_mentah'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductionItemStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ProductionItemMovement::class);
    }

    public function displayType(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Gudang tempat sisa potong yang masih layak pakai dikumpulkan. */
    public function menampungSisa(): bool
    {
        return $this->type === 'bahan_sisa';
    }

    /** Nilai seluruh isinya, memakai harga per satuan pakai tiap barang. */
    public function stockValue(): float
    {
        return $this->stocks()->with('item')->get()
            ->sum(fn (ProductionItemStock $s) => (float) $s->qty * ($s->item?->basePrice() ?? 0));
    }
}
