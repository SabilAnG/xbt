<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcurementHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Arsip "Riwayat Pengadaan" — hanya untuk acuan harga. */
class ProcurementHistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = ProcurementHistory::query()
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")
            ))
            ->when($request->input('period'), fn ($query, $period) => $query->where('period', $period))
            ->when($request->input('category'), fn ($query, $category) => $query->where('category', $category));

        return view('admin.procurement.index', [
            'total' => (clone $query)->sum('total'),
            'rows' => $query->orderBy('number')->paginate(50)->withQueryString(),
            'periods' => ProcurementHistory::query()->distinct()->orderBy('period')->pluck('period')->filter(),
            'categories' => ProcurementHistory::query()->distinct()->orderBy('category')->pluck('category')->filter(),
        ]);
    }
}
