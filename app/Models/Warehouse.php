<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empat gudang alur produksi: bahan baku, setengah jadi, stok sisa produksi,
 * dan finish good.
 */
class Warehouse extends Model
{
    public const RAW = 'bahan_baku';

    public const SEMI = 'setengah_jadi';

    public const ACCESSORY = 'aksesoris';

    public const OFFCUT = 'sisa_produksi';

    public const VENDOR = 'di_vendor';

    public const FINISHED = 'finish_good';

    /** Jenis gudang => [slug URL, label, keterangan]. */
    public const TYPES = [
        self::RAW => ['bahan-baku', 'Bahan Baku', 'Bahan mentah yang dibeli: pipa batangan, plat, gaswool.'],
        self::SEMI => ['setengah-jadi', 'Setengah Jadi', 'Barang beli jadi (pureng, plendes) dan hasil proses vendor/produksi (leheran bending).'],
        self::ACCESSORY => ['aksesoris', 'Aksesoris', 'Baut, stiker/emblem, tameng, karet, packing, dus.'],
        self::OFFCUT => ['stok-sisa', 'Stok Sisa Produksi', 'Potongan sisa pipa dari produksi yang masih bisa dipakai lagi.'],
        self::VENDOR => ['di-vendor', 'Di Vendor', 'Barang yang sedang dikerjakan vendor (bending, chrome, cat).'],
        self::FINISHED => ['finish-good', 'Finish Good', 'Knalpot jadi hasil produksi yang siap dijual.'],
    ];

    protected $fillable = ['name', 'code', 'type', 'address', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Gudang untuk satu jenis (kunci jenis atau slug URL); dibuat bila belum ada. */
    public static function forType(string $type): self
    {
        $key = collect(self::TYPES)->search(fn (array $meta, string $key) => $key === $type || $meta[0] === $type);

        abort_if($key === false, 404);

        return static::firstOrCreate(['type' => $key], [
            'name' => 'Gudang '.self::TYPES[$key][1],
            'code' => 'GD-'.strtoupper(str_replace('_', '-', $key)),
            'is_active' => true,
        ]);
    }

    /**
     * Gudang asal bahan saat dibeli: bahan baku, setengah jadi, aksesoris.
     *
     * @return Collection<int, self>
     */
    public static function storage(): Collection
    {
        return new Collection([self::forType(self::RAW), self::forType(self::SEMI), self::forType(self::ACCESSORY)]);
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_column(self::TYPES, 0);
    }

    public function slug(): string
    {
        return self::TYPES[$this->type][0] ?? (string) $this->type;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][1] ?? (string) $this->type;
    }

    public function typeDescription(): string
    {
        return self::TYPES[$this->type][2] ?? (string) $this->description;
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MaterialStock::class);
    }
}
