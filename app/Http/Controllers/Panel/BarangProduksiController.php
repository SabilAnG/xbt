<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ProductionItem;
use App\Models\ProductionItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Barang produksi di panel baru.
 *
 * Pencarian, penyaringan, dan pengurutan semuanya lewat query string — bukan
 * state di JavaScript. Artinya hasil pencarian bisa disalin sebagai tautan,
 * tombol kembali peramban bekerja, dan halaman tetap berguna tanpa JS.
 */
class BarangProduksiController extends Controller
{
    /** Kolom yang boleh diurutkan. Daftar putih, karena isinya masuk ke query. */
    private const URUTAN = ['name', 'sku', 'cost_price', 'stock'];

    /** Isian ukuran yang diketik dalam satuan pilihan, lalu disimpan dalam mm. */
    private const UKURAN_MM = ['length_mm', 'width_mm', 'diameter_mm', 'thickness_mm'];

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));
        $jenis = $request->query('jenis');
        $urut = in_array($request->query('urut'), self::URUTAN, true) ? $request->query('urut') : 'name';
        $arah = $request->query('arah') === 'desc' ? 'desc' : 'asc';

        return view('panel.barang-produksi.index', [
            'barang' => ProductionItem::query()
                ->with('category')
                ->when($cari !== '', fn ($q) => $q->where(
                    fn ($q) => $q->where('name', 'like', "%{$cari}%")->orWhere('sku', 'like', "%{$cari}%")
                ))
                ->when($jenis, fn ($q) => $q->where('production_item_category_id', $jenis))
                ->orderBy($urut, $arah)
                ->paginate(25)
                ->withQueryString(),
            'cari' => $cari,
            'jenis' => $jenis,
            'urut' => $urut,
            'arah' => $arah,
            'daftarJenis' => $this->jenis(),
        ]);
    }

    public function create(): View
    {
        return view('panel.barang-produksi.form', [
            'barang' => new ProductionItem,
            'daftarJenis' => $this->jenis(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $barang = ProductionItem::create($this->bersihkan($request));

        return redirect()
            ->route('panel.barang-produksi.index')
            ->with('sukses', "Barang \"{$barang->name}\" ditambahkan.");
    }

    public function edit(ProductionItem $barangProduksi): View
    {
        return view('panel.barang-produksi.form', [
            'barang' => $barangProduksi,
            'daftarJenis' => $this->jenis(),
        ]);
    }

    public function update(Request $request, ProductionItem $barangProduksi): RedirectResponse
    {
        $barangProduksi->update($this->bersihkan($request, $barangProduksi));

        return redirect()
            ->route('panel.barang-produksi.index')
            ->with('sukses', "Barang \"{$barangProduksi->name}\" disimpan.");
    }

    /**
     * Barang yang stoknya belum nol tidak boleh hilang begitu saja — nilainya
     * ikut lenyap tanpa jejak. Nolkan lewat stok opname dulu, supaya
     * penyusutannya tercatat sebagai koreksi, bukan sebagai data yang raib.
     */
    public function destroy(ProductionItem $barangProduksi): RedirectResponse
    {
        if (abs((float) $barangProduksi->stock) > 0.0001) {
            return back()->with('gagal', "Stoknya masih {$barangProduksi->displayStock()}. Nolkan lewat stok opname dulu.");
        }

        $nama = $barangProduksi->name;
        $barangProduksi->delete();

        return redirect()
            ->route('panel.barang-produksi.index')
            ->with('sukses', "Barang \"{$nama}\" dihapus.");
    }

    /** @return Collection<int|string, string> */
    private function jenis()
    {
        return ProductionItemCategory::orderBy('name')->pluck('name', 'id');
    }

    /**
     * Validasi, lalu ubah ukuran yang diketik jadi milimeter.
     *
     * Ukuran WAJIB hanya untuk bentuk yang konversi satuannya bergantung
     * padanya. Pipa Rp600.000 per batang tanpa panjang akan terbaca Rp600.000
     * per milimeter, dan modal formula meleset ribuan kali lipat.
     *
     * @return array<string, mixed>
     */
    private function bersihkan(Request $request, ?ProductionItem $abaikan = null): array
    {
        $bentuk = $request->input('shape');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('production_items', 'sku')->ignore($abaikan)],
            'production_item_category_id' => ['nullable', 'exists:production_item_categories,id'],
            'source' => ['required', Rule::in(array_keys(ProductionItem::SOURCES))],
            'role' => ['required', Rule::in(array_keys(ProductionItem::ROLES))],
            'shape' => ['required', Rule::in(array_keys(ProductionItem::SHAPES))],
            'material' => ['nullable', Rule::in(array_keys(ProductionItem::MATERIALS))],
            'unit' => ['required', 'string', 'max:255'],
            'size_unit' => ['required', Rule::in(array_keys(ProductionItem::SIZE_UNITS))],
            'length_mm' => [Rule::requiredIf(in_array($bentuk, ['linear', 'sheet'], true)), 'nullable', 'numeric', 'min:0'],
            'width_mm' => [Rule::requiredIf($bentuk === 'sheet'), 'nullable', 'numeric', 'min:0'],
            'diameter_mm' => ['nullable', 'numeric', 'min:0'],
            'thickness_mm' => ['nullable', 'numeric', 'min:0'],
            'weight_gram' => [Rule::requiredIf($bentuk === 'weight'), 'nullable', 'numeric', 'min:0'],
            'volume_ml' => [Rule::requiredIf($bentuk === 'volume'), 'nullable', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'min_reusable' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ], [], [
            'length_mm' => 'panjang',
            'width_mm' => 'lebar',
            'weight_gram' => 'berat',
            'volume_ml' => 'volume',
            'cost_price' => 'harga',
        ]);

        // Diketik dalam satuan pilihan, DISIMPAN dalam mm — supaya tidak ada
        // perhitungan di tempat lain yang perlu tahu soal satuan ini.
        foreach (self::UKURAN_MM as $medan) {
            if (filled($data[$medan] ?? null)) {
                $data[$medan] = ProductionItem::toMm((float) $data[$medan], $data['size_unit']);
            }
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['min_stock'] ??= 0;
        $data['min_reusable'] ??= 0;

        return $data;
    }
}
