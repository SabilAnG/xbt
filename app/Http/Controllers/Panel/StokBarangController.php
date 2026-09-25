<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Stok barang jadi di panel baru.
 *
 * Terpisah dari Barang Produksi: yang ini barang yang dijual, yang itu bahan
 * untuk membuatnya. Satu barang boleh tidak pernah tampil di website, dan
 * sebaliknya — kolom product_id yang menautkan keduanya bila memang sama.
 *
 * `stock` TIDAK bisa diketik di sini. Angkanya cache dari kartu stok, dan
 * isian yang bisa diketik akan membuatnya berselisih dengan mutasinya sendiri.
 */
class StokBarangController extends Controller
{
    private const URUTAN = ['name', 'sku', 'cost_price', 'sell_price', 'stock'];

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));
        $kategori = $request->query('kategori');
        $menipis = $request->boolean('menipis');
        $urut = in_array($request->query('urut'), self::URUTAN, true) ? $request->query('urut') : 'name';
        $arah = $request->query('arah') === 'desc' ? 'desc' : 'asc';

        return view('panel.stok-barang.index', [
            'barang' => Item::query()
                ->with(['category', 'type'])
                ->when($cari !== '', fn ($q) => $q->where(
                    fn ($q) => $q->where('name', 'like', "%{$cari}%")->orWhere('sku', 'like', "%{$cari}%")
                ))
                ->when($kategori, fn ($q) => $q->where('item_category_id', $kategori))
                ->when($menipis, fn ($q) => $q->lowStock())
                ->orderBy($urut, $arah)
                ->paginate(25)
                ->withQueryString(),
            'cari' => $cari,
            'kategori' => $kategori,
            'menipis' => $menipis,
            'urut' => $urut,
            'arah' => $arah,
            'daftarKategori' => ItemCategory::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('panel.stok-barang.form', $this->bekal(new Item));
    }

    public function edit(Item $stokBarang): View
    {
        return view('panel.stok-barang.form', $this->bekal($stokBarang));
    }

    public function store(Request $request): RedirectResponse
    {
        $barang = Item::create($this->bersihkan($request));

        return redirect()->route('panel.stok-barang.index')
            ->with('sukses', "Barang \"{$barang->name}\" ditambahkan.");
    }

    public function update(Request $request, Item $stokBarang): RedirectResponse
    {
        $stokBarang->update($this->bersihkan($request, $stokBarang));

        return redirect()->route('panel.stok-barang.index')
            ->with('sukses', "Barang \"{$stokBarang->name}\" disimpan.");
    }

    /**
     * Barang yang stoknya belum nol tidak boleh hilang begitu saja — nilainya
     * ikut lenyap tanpa jejak. Nolkan lewat stok opname dulu, supaya
     * penyusutannya tercatat sebagai koreksi, bukan sebagai data yang raib.
     */
    public function destroy(Item $stokBarang): RedirectResponse
    {
        if (abs((float) $stokBarang->stock) > 0.0001) {
            return back()->with('gagal', "Stoknya masih {$stokBarang->stock}. Nolkan lewat stok opname dulu.");
        }

        $nama = $stokBarang->name;
        $stokBarang->delete();

        return redirect()->route('panel.stok-barang.index')
            ->with('sukses', "Barang \"{$nama}\" dihapus.");
    }

    // ------------------------------------------------------------- internal

    /** @return array<string, mixed> */
    private function bekal(Item $barang): array
    {
        return [
            'barang' => $barang,
            'daftarKategori' => ItemCategory::orderBy('name')->pluck('name', 'id'),
            'daftarJenis' => ItemType::orderBy('name')->pluck('name', 'id'),
        ];
    }

    /** @return array<string, mixed> */
    private function bersihkan(Request $request, ?Item $abaikan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('items', 'sku')->ignore($abaikan)],
            'item_category_id' => ['nullable', 'exists:item_categories,id'],
            'item_type_id' => ['nullable', 'exists:item_types,id'],
            'unit' => ['required', 'string', 'max:255'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ], [], [
            'cost_price' => 'harga modal',
            'sell_price' => 'harga jual',
        ]);

        $data['min_stock'] ??= 0;
        $data['is_active'] = $request->boolean('is_active');

        // `stock` sengaja TIDAK ada di sini walau kolomnya fillable: angkanya
        // hanya boleh bergerak lewat pembelian, penjualan, atau stok opname.
        return $data;
    }
}
