<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ProductionItem;
use App\Models\ProductionPurchase;
use App\Models\Wallet;
use App\Models\Warehouse;
use App\Services\DocumentNumber;
use App\Services\ProductionStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        return view('panel.pembelian-bahan.show', [
            'nota' => $pembelianBahan,
            'daftarBahan' => ProductionItem::where('is_active', true)->orderBy('name')->get(),
            'daftarGudang' => Warehouse::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('panel.pembelian-bahan.form', [
            'nomor' => DocumentNumber::next('production_purchases'),
            'daftarGudang' => Warehouse::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'daftarKas' => Wallet::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:64', Rule::unique('production_purchases', 'invoice_number')],
            'purchased_at' => ['required', 'date'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'wallet_id' => ['nullable', 'exists:wallets,id'],
            'notes' => ['nullable', 'string'],
        ], [
            'warehouse_id.required' => 'Bahan yang dibeli harus mendarat di sebuah gudang.',
        ], ['invoice_number' => 'nomor nota', 'warehouse_id' => 'gudang']);

        $nota = ProductionPurchase::create($data + ['status' => 'draft']);

        return redirect()->route('panel.pembelian-bahan.show', $nota)
            ->with('sukses', 'Nota dibuat sebagai draft. Tambahkan bahannya di sini.');
    }

    public function tambahBaris(Request $request, ProductionPurchase $pembelianBahan): RedirectResponse
    {
        if ($pembelianBahan->isPosted()) {
            return back()->with('gagal', 'Nota yang sudah dibukukan tidak bisa diubah. Batalkan dulu pembukuannya.');
        }

        $data = $request->validate([
            'production_item_id' => ['required', 'exists:production_items,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'qty' => ['required', 'numeric', 'min:0.001'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
        ], [], ['production_item_id' => 'bahan', 'qty' => 'jumlah', 'unit_cost' => 'harga']);

        // Gudang baris boleh kosong; yang di kepala nota jadi cadangannya.
        $pembelianBahan->items()->create($data + ['warehouse_id' => $data['warehouse_id'] ?? $pembelianBahan->warehouse_id]);
        $pembelianBahan->recalculateTotals();

        return back()->with('sukses', 'Bahan ditambahkan ke nota.');
    }

    public function hapusBaris(ProductionPurchase $pembelianBahan, int $baris): RedirectResponse
    {
        if ($pembelianBahan->isPosted()) {
            return back()->with('gagal', 'Nota yang sudah dibukukan tidak bisa diubah.');
        }

        $pembelianBahan->items()->whereKey($baris)->delete();
        $pembelianBahan->recalculateTotals();

        return back()->with('sukses', 'Baris dihapus.');
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
