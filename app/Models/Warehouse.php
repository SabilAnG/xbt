<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gudang: Gudang Komponen, Gudang Finishing, dst.
 */
class Warehouse extends Model
{
    protected $fillable = ['name', 'code', 'address', 'description', 'is_active', 'sort_order'];

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
