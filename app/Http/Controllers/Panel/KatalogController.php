<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Katalog website di panel baru.
 *
 * Yang tampil di halaman produk situs publik. Terpisah dari Stok Barang:
 * satu barang boleh tidak pernah tampil di website, dan sebaliknya.
 *
 * Gambar disimpan di disk `site` yang berakar di public/ — itu sebabnya path
 * yang tersimpan relatif terhadap public dan bisa dipanggil `asset()` langsung,
 * sama seperti foto lama yang dibawa dari situs sebelumnya.
 */
class KatalogController extends Controller
{
    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));

        return view('panel.katalog.index', [
            'produk' => Product::query()
                ->withCount('images')
                ->when($cari !== '', fn ($q) => $q->where(
                    fn ($q) => $q->where('name', 'like', "%{$cari}%")->orWhere('slug', 'like', "%{$cari}%")
                ))
                ->orderBy('sort_order')->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'cari' => $cari,
        ]);
    }

    public function create(): View
    {
        return view('panel.katalog.form', ['produk' => new Product]);
    }

    public function edit(Product $katalog): View
    {
        $katalog->load('images');

        return view('panel.katalog.form', ['produk' => $katalog]);
    }

    public function store(Request $request): RedirectResponse
    {
        $produk = Product::create($this->bersihkan($request));

        return redirect()->route('panel.katalog.edit', $produk)
            ->with('sukses', "Produk \"{$produk->name}\" dibuat. Gambarnya bisa ditambahkan di sini.");
    }

    public function update(Request $request, Product $katalog): RedirectResponse
    {
        $katalog->update($this->bersihkan($request, $katalog));

        return redirect()->route('panel.katalog.index')
            ->with('sukses', "Produk \"{$katalog->name}\" disimpan.");
    }

    public function destroy(Product $katalog): RedirectResponse
    {
        $nama = $katalog->name;

        // Berkas gambarnya ikut dibuang. Baris di database hilang bersama
        // produknya lewat cascade, tapi berkasnya tidak — dan folder yang
        // penuh gambar yatim tidak pernah ada yang membersihkan.
        foreach ($katalog->images as $gambar) {
            Storage::disk('site')->delete(array_filter([$gambar->path, $gambar->thumb_path]));
        }

        $katalog->delete();

        return redirect()->route('panel.katalog.index')
            ->with('sukses', "Produk \"{$nama}\" dihapus.");
    }

    /** Unggah satu atau beberapa gambar sekaligus. */
    public function unggah(Request $request, Product $katalog): RedirectResponse
    {
        $request->validate([
            'gambar' => ['required', 'array', 'max:10'],
            'gambar.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], ['gambar' => 'gambar']);

        $urut = (int) $katalog->images()->max('sort_order');

        foreach ($request->file('gambar') as $berkas) {
            // Nama berkas dibuat sendiri, bukan memakai nama aslinya: nama dari
            // peramban bisa mengandung apa saja, dan dua orang yang mengunggah
            // "foto.jpg" akan saling menimpa.
            $nama = Str::ulid().'.'.$berkas->extension();
            $path = $berkas->storeAs("storage/produk/{$katalog->id}", $nama, 'site');

            $katalog->images()->create(['path' => $path, 'sort_order' => ++$urut]);
        }

        return back()->with('sukses', count($request->file('gambar')).' gambar diunggah.');
    }

    public function hapusGambar(Product $katalog, ProductImage $gambar): RedirectResponse
    {
        // Rujukan silang diperiksa: id gambar datang dari alamat, dan tanpa ini
        // gambar milik produk lain bisa dihapus lewat produk ini.
        abort_unless($gambar->product_id === $katalog->id, 404);

        Storage::disk('site')->delete(array_filter([$gambar->path, $gambar->thumb_path]));
        $gambar->delete();

        return back()->with('sukses', 'Gambar dihapus.');
    }

    // ------------------------------------------------------------- internal

    /** @return array<string, mixed> */
    private function bersihkan(Request $request, ?Product $abaikan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($abaikan)],
            'fitment' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], ['price' => 'harga']);

        $data['slug'] = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : Str::slug($data['name']);
        $data['sort_order'] ??= 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
