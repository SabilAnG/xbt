<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Material;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Master Item: satu daftar untuk semua jenis barang. Datanya tetap di dua
 * tabel — bahan (materials) dan produk jadi (items) — karena penjualan dan
 * katalog website memakai produk jadi.
 */
class MasterItemController extends Controller
{
    /** Jenis item => label. */
    public const TYPES = [
        'bahan-baku' => 'Bahan Baku',
        'pipa' => 'Pipa',
        'setengah-jadi' => 'Setengah Jadi',
        'aksesoris' => 'Aksesoris',
        'produk-jadi' => 'Produk Jadi',
    ];

    public function index(Request $request): View
    {
        $type = $request->input('jenis');
        $search = $request->string('q')->trim()->value();
        $rows = $this->rows($search);

        return view('admin.master-items.index', [
            'rows' => $type ? $rows->where('type', $type)->values() : $rows,
            'counts' => $rows->countBy('type'),
            'total' => $rows->count(),
            'type' => $type,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(?string $search): Collection
    {
        $semi = Warehouse::forType(Warehouse::SEMI)->id;
        $accessory = Warehouse::forType(Warehouse::ACCESSORY)->id;

        $materials = Material::query()->with(['category', 'warehouse'])
            ->when($search, fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('spec', 'like', "%{$search}%")))
            ->orderBy('name')->get()
            ->map(fn (Material $material) => [
                'type' => match (true) {
                    $material->isPipe() => 'pipa',
                    $material->warehouse_id === $semi => 'setengah-jadi',
                    $material->warehouse_id === $accessory => 'aksesoris',
                    default => 'bahan-baku',
                },
                'code' => $material->code,
                'name' => $material->name,
                'spec' => $material->spec,
                'category' => $material->category?->name,
                'warehouse' => $material->warehouse?->typeLabel(),
                'units' => $material->purchaseUnit().($material->conversionLabel() ? ' → '.$material->stockUnit() : ''),
                'conversion' => $material->conversionLabel(),
                'price' => $material->unitCost(),
                'price_unit' => $material->stockUnit(),
                'stock' => (float) $material->stock,
                'stock_unit' => $material->stockUnit(),
                'status' => $material->is_active ? $material->stockStatus() : 'NONAKTIF',
                'active' => $material->is_active,
                'show' => route('admin.materials.show', $material),
                'edit' => route('admin.materials.edit', $material),
            ]);

        $items = Item::query()->with('formula')
            ->when($search, fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->orderBy('name')->get()
            ->map(fn (Item $item) => [
                'type' => 'produk-jadi',
                'code' => $item->sku,
                'name' => $item->name,
                'spec' => $item->formula ? 'Formula: '.$item->formula->name : null,
                'category' => $item->brand,
                'warehouse' => Warehouse::TYPES[Warehouse::FINISHED][1],
                'units' => (string) $item->unit,
                'conversion' => null,
                'price' => (float) $item->cost_price,
                'price_unit' => (string) $item->unit,
                'stock' => (float) $item->stock,
                'stock_unit' => (string) $item->unit,
                'status' => $item->is_active ? $item->stockStatus() : 'NONAKTIF',
                'active' => $item->is_active,
                'show' => route('admin.items.show', $item),
                'edit' => route('admin.items.edit', $item),
            ]);

        $order = array_flip(array_keys(self::TYPES));

        return $materials->concat($items)->sortBy(fn (array $row) => [$order[$row['type']], mb_strtolower($row['name'])])->values();
    }
}
