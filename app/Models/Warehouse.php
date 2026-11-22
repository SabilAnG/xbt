<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gudang: Gudang Komponen, Gudang Finishing, dst.
 */
class Warehouse extends Model
{
    /** Jenis gudang => [slug URL, label]. */
    public const TYPES = [
        'bahan_baku' => ['bahan-baku', 'Bahan Baku'],
        'setengah_jadi' => ['setengah-jadi', 'Setengah Jadi'],
        'finish_good' => ['finish-good', 'Finish Good'],
    ];

    protected $fillable = ['name', 'code', 'type', 'address', 'description', 'is_active', 'sort_order'];

    /** Gudang untuk satu jenis (slug URL atau kunci jenis); dibuat bila belum ada. */
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

    public function slug(): string
    {
        return self::TYPES[$this->type][0] ?? (string) $this->type;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][1] ?? (string) $this->type;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }

    /** Nilai seluruh bahan yang tersimpan di gudang ini. */
    public function stockValue(): float
    {
        return (float) MaterialStock::query()
            ->join('racks', 'racks.id', '=', 'material_stocks.rack_id')
            ->join('materials', 'materials.id', '=', 'material_stocks.material_id')
            ->where('racks.warehouse_id', $this->id)
            ->sum(\DB::raw('material_stocks.qty * materials.cost_price'));
    }
}
