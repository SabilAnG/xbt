<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Material;
use App\Models\Production;
use App\Models\Sale;
use App\Services\DashboardSummary;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardSummary $summary): View
    {
        $alertMaterials = Material::query()->where('is_active', true)->orderBy('stock')->get()
            ->filter(fn (Material $material) => $material->stockStatus() !== 'AMAN')
            ->values();

        $lowProducts = Item::query()->where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0)
            ->orderBy('stock')->get();

        return view('admin.dashboard', [
            'headline' => $summary->headline(),
            'monthly' => $summary->monthly(),
            'byCategory' => $summary->expensesByCategory(),
            'byBrand' => $summary->byBrand(),
            'alertMaterials' => $alertMaterials,
            'lowProducts' => $lowProducts,
            'topStock' => Item::query()->where('stock', '>', 0)->orderByDesc('stock')->limit(8)->get(),
            'recentProductions' => Production::query()->with('item', 'formula')->latest('produced_at')->latest('id')->limit(5)->get(),
            'recentSales' => Sale::query()->with('items.item')->latest('sold_at')->latest('id')->limit(5)->get(),
        ]);
    }
}
