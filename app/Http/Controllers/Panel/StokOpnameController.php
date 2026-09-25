<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ProductionItemOpname;
use App\Services\ProductionStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hitung ulang stok bahan (opname) di panel baru.
 *
 * Pola yang sama dengan Pembelian Bahan: draft tidak menyentuh apa pun, dan
 * pembukuan diserahkan sepenuhnya ke ProductionStockService. Bedanya satu —
 * yang dikoreksi di sini SELISIH, bukan penambahan. Hanya baris yang berbeda
 * antara hitungan fisik dan catatan sistem yang menggerakkan stok.
 */
class StokOpnameController extends Controller
{
    public function __construct(private readonly ProductionStockService $stok) {}

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));
        $status = in_array($request->query('status'), array_keys(ProductionItemOpname::STATUSES), true)
            ? $request->query('status')
            : null;

        return view('panel.stok-opname.index', [
            'opname' => ProductionItemOpname::query()
                ->with('warehouse')
                ->withCount('items')
                ->when($cari !== '', fn ($q) => $q->where(
                    fn ($q) => $q->where('opname_number', 'like', "%{$cari}%")
                        ->orWhere('counted_by', 'like', "%{$cari}%")
                ))
                ->when($status, fn ($q) => $q->where('status', $status))
                ->orderByDesc('opname_date')
                ->paginate(25)
                ->withQueryString(),
            'cari' => $cari,
            'status' => $status,
        ]);
    }

    public function show(ProductionItemOpname $stokOpname): View
    {
        $stokOpname->load(['items.item', 'warehouse']);

        return view('panel.stok-opname.show', ['opname' => $stokOpname]);
    }

    public function bukukan(ProductionItemOpname $stokOpname): RedirectResponse
    {
        if ($stokOpname->isPosted()) {
            return back()->with('gagal', 'Opname ini sudah dibukukan.');
        }

        $this->stok->postOpname($stokOpname);

        return back()->with('sukses', "Opname {$stokOpname->opname_number} dibukukan. Hanya baris yang selisih yang mengoreksi stok.");
    }

    public function batalkan(ProductionItemOpname $stokOpname): RedirectResponse
    {
        if (! $stokOpname->isPosted()) {
            return back()->with('gagal', 'Opname ini masih draft, tidak ada yang perlu dibatalkan.');
        }

        $this->stok->unpostOpname($stokOpname);

        return back()->with('sukses', "Pembukuan opname {$stokOpname->opname_number} dibatalkan.");
    }
}
