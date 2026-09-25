<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ProductionPurchase;
use App\Services\ProductionStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pembelian bahan di panel baru.
 *
 * Nota berstatus draft tidak menyentuh apa pun; stok dan kas baru bergerak
 * setelah dibukukan. Pembukuan TIDAK ditulis ulang di sini — seluruhnya
 * diserahkan ke ProductionStockService, satu-satunya tempat yang boleh menulis
 * kartu stok. Menyalin logikanya ke panel baru berarti dua tempat yang harus
 * sama selamanya, dan yang satu pasti tertinggal.
 */
class PembelianBahanController extends Controller
{
    private const URUTAN = ['purchased_at', 'invoice_number', 'total'];

    public function __construct(private readonly ProductionStockService $stok) {}

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));
        $status = in_array($request->query('status'), array_keys(ProductionPurchase::STATUSES), true)
            ? $request->query('status')
            : null;
        $urut = in_array($request->query('urut'), self::URUTAN, true) ? $request->query('urut') : 'purchased_at';
        $arah = $request->query('arah') === 'asc' ? 'asc' : 'desc';

        return view('panel.pembelian-bahan.index', [
            'nota' => ProductionPurchase::query()
                ->with(['warehouse', 'wallet'])
                ->withCount('items')
                ->when($cari !== '', fn ($q) => $q->where(
                    fn ($q) => $q->where('invoice_number', 'like', "%{$cari}%")
                        ->orWhere('supplier_name', 'like', "%{$cari}%")
                ))
                ->when($status, fn ($q) => $q->where('status', $status))
                ->orderBy($urut, $arah)
                ->paginate(25)
                ->withQueryString(),
            'cari' => $cari,
            'status' => $status,
            'urut' => $urut,
            'arah' => $arah,
        ]);
    }

    public function show(ProductionPurchase $pembelianBahan): View
    {
        $pembelianBahan->load(['items.item', 'items.warehouse', 'warehouse', 'wallet']);

        return view('panel.pembelian-bahan.show', ['nota' => $pembelianBahan]);
    }

    /**
     * Bukukan nota: stok gudang naik, kas turun.
     *
     * Dijaga dua kali — tombolnya memang hanya muncul untuk draft, tapi alamat
     * POST tetap bisa dikirim langsung, dan membukukan nota yang sudah
     * dibukukan berarti stok naik dua kali tanpa ada yang menyadarinya.
     */
    public function bukukan(ProductionPurchase $pembelianBahan): RedirectResponse
    {
        if ($pembelianBahan->isPosted()) {
            return back()->with('gagal', 'Nota ini sudah dibukukan.');
        }

        if ($pembelianBahan->items()->count() === 0) {
            return back()->with('gagal', 'Nota tanpa baris barang tidak bisa dibukukan.');
        }

        $this->stok->postPurchase($pembelianBahan);

        return back()->with('sukses', "Nota {$pembelianBahan->invoice_number} dibukukan. Stok dan kas sudah bergerak.");
    }

    /**
     * Batalkan pembukuan: jejak dokumen ini dihapus, lalu stok dihitung ulang
     * dari baris yang tersisa. Menghitung ulang lebih aman daripada menulis
     * mutasi kebalikan — hasilnya tetap benar walau ada dokumen lain yang
     * dibukukan sesudahnya.
     */
    public function batalkan(ProductionPurchase $pembelianBahan): RedirectResponse
    {
        if (! $pembelianBahan->isPosted()) {
            return back()->with('gagal', 'Nota ini masih draft, tidak ada yang perlu dibatalkan.');
        }

        $this->stok->unpostPurchase($pembelianBahan);

        return back()->with('sukses', "Pembukuan nota {$pembelianBahan->invoice_number} dibatalkan.");
    }
}
