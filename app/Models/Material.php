<?php

namespace App\Models;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Barang persediaan untuk produksi.
 *
 * Bahan per satuan (pcs, kg, lembar) stoknya angka biasa. Pipa (measure
 * `length`) dicatat per batang/potongan lewat PipePiece; stoknya = total cm
 * potongan yang masih tersedia, dan harganya per cm.
 */
class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    public const UNIT = 'unit';

    public const LENGTH = 'length';

    public const MEASURES = [
        self::UNIT => 'Per satuan (pcs, set, kg, lembar)',
        self::LENGTH => 'Pipa: batang & panjang (cm)',
    ];

    /** Lebar mata gergaji potong (mm) bila belum diatur. */
    public const DEFAULT_KERF_MM = 3.0;

    protected $fillable = [
        'code', 'name', 'material_category_id', 'warehouse_id', 'measure', 'unit', 'use_unit', 'conversion', 'spec',
        'bar_length_cm', 'min_reusable_cm', 'avg_cost', 'last_price', 'stock', 'min_stock', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conversion' => 'decimal:4',
            'bar_length_cm' => 'decimal:2',
            'min_reusable_cm' => 'decimal:2',
            'avg_cost' => 'decimal:4',
            'last_price' => 'decimal:2',
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /** Lebar potong gergaji dalam cm (Pengaturan Produksi menyimpannya dalam mm). */
    public static function kerfCm(): float
    {
        return (float) Setting::get('produksi.kerf_mm', self::DEFAULT_KERF_MM) / 10;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MaterialStock::class);
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(PipePiece::class);
    }

    public function availablePieces(): HasMany
    {
        return $this->pieces()->where('status', PipePiece::AVAILABLE);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(MaterialMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isPipe(): bool
    {
        return $this->measure === self::LENGTH;
    }

    /** Satuan pakai = satuan stok & kebutuhan formula (cm untuk pipa, mis. cm² untuk plat). */
    public function stockUnit(): string
    {
        if ($this->isPipe()) {
            return 'cm';
        }

        return (string) ($this->hasConversion() ? $this->use_unit : $this->unit);
    }

    /** Satuan beli: batang untuk pipa, mis. lembar untuk plat. */
    public function purchaseUnit(): string
    {
        return $this->isPipe() ? 'batang' : (string) $this->unit;
    }

    /** Bahan per satuan yang satuan belinya berbeda dari satuan pakainya. */
    public function hasConversion(): bool
    {
        return ! $this->isPipe() && filled($this->use_unit) && (float) $this->conversion > 0 && (float) $this->conversion !== 1.0;
    }

    /** Isi satuan pakai dalam 1 satuan beli (panjang batang untuk pipa). */
    public function conversionFactor(): float
    {
        if ($this->isPipe()) {
            return max((float) $this->bar_length_cm, 0.0);
        }

        return $this->hasConversion() ? (float) $this->conversion : 1.0;
    }

    /** Ubah jumlah satuan beli ke satuan pakai. */
    public function toStockQty(float $purchaseQty): float
    {
        return $purchaseQty * $this->conversionFactor();
    }

    /** Teks konversi, mis. "1 Lembar = 28.800 cm²". */
    public function conversionLabel(): ?string
    {
        if ($this->isPipe()) {
            return '1 batang = '.qty($this->bar_length_cm, 2).' cm';
        }

        return $this->hasConversion() ? '1 '.$this->unit.' = '.qty($this->conversion, 4).' '.$this->use_unit : null;
    }

    /** Harga per satuan pakai (per cm untuk pipa) yang dibawa ke formula. */
    public function unitCost(): float
    {
        if ((float) $this->avg_cost > 0) {
            return (float) $this->avg_cost;
        }

        $factor = $this->conversionFactor();

        return $factor > 0 ? (float) $this->last_price / $factor : 0.0;
    }

    /** Harga per satuan beli (per batang untuk pipa), acuan saat belanja. */
    public function purchaseUnitCost(): float
    {
        if ((float) $this->last_price > 0) {
            return (float) $this->last_price;
        }

        return $this->unitCost() * $this->conversionFactor();
    }

    public function stockIn(Warehouse|int $warehouse): float
    {
        $id = $warehouse instanceof Warehouse ? $warehouse->id : $warehouse;

        return (float) $this->stocks->firstWhere('warehouse_id', $id)?->qty;
    }

    /**
     * Gudang yang boleh menerima bahan ini dari pembelian: bahan per satuan ke
     * Bahan Baku atau Setengah Jadi; pipa ke Bahan Baku (batang utuh) atau
     * Stok Sisa (potongan).
     *
     * @return list<int>
     */
    public function allowedWarehouseIds(): array
    {
        $types = $this->isPipe() ? [Warehouse::RAW, Warehouse::OFFCUT] : [Warehouse::RAW, Warehouse::SEMI, Warehouse::ACCESSORY];

        return collect($types)->map(fn (string $type) => Warehouse::forType($type)->id)->all();
    }

    public function stockStatus(): string
    {
        return stock_status((float) $this->stock, (float) $this->min_stock);
    }

    public function label(): string
    {
        return trim($this->name.($this->spec ? ' · '.$this->spec : ''));
    }
}
