<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\StoresSiteUploads;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Katalog produk yang tampil di halaman /products website. */
class WebProductController extends Controller
{
    use StoresSiteUploads;

    private const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private const VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/quicktime'];

    private const PHOTO_MAX_KB = 5 * 1024;

    private const VIDEO_MAX_KB = 50 * 1024;

    public function index(Request $request): View
    {
        $products = Product::query()->with(['images', 'item'])
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->ordered()
            ->paginate(25)->withQueryString();

        return view('admin.website.products.index', ['products' => $products]);
    }

    public function create(): View
    {
        return view('admin.website.products.form', [
            'product' => new Product(['is_active' => true, 'currency' => 'IDR', 'sort_order' => (int) Product::max('sort_order') + 1]),
            'items' => Item::where('is_active', true)->orderBy('sku')->get(),
        ]);
    }

    /** Daftar knalpot di Stok Produk yang bisa diunggah ke website. */
    public function fromStock(Request $request): View
    {
        $items = Item::query()
            ->when($request->string('q')->trim()->value(), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($request->boolean('available'), fn ($query) => $query->where('stock', '>', 0))
            ->where('is_active', true)
            ->orderBy('sku')
            ->get();

        return view('admin.website.products.from-stock', [
            'items' => $items,
            'published' => Product::query()->whereNotNull('item_id')->get()->keyBy('item_id'),
        ]);
    }

    /**
     * Pratinjau halaman produk website dari isian form yang belum disimpan.
     * Foto/video yang baru dipilih dikirim sebagai penanda `__pending__/n`;
     * halaman admin menggantinya dengan berkas lokal di dalam iframe.
     */
    public function preview(Request $request): View
    {
        $product = $request->filled('product_id')
            ? Product::with('images')->findOrFail($request->integer('product_id'))
            : new Product;

        $product->forceFill([
            'name' => $request->input('name') ?: 'Nama produk',
            'slug' => Str::slug($request->input('slug') ?: $request->input('name')) ?: 'pratinjau',
            'fitment' => $request->input('fitment'),
            'price' => (float) $request->input('price'),
            'currency' => $request->input('currency') === 'USD' ? 'USD' : 'IDR',
            'description' => $request->input('description'),
            'item_id' => $request->input('item_id') ?: null,
        ]);

        $rows = $request->input('images', []);
        $media = $product->images
            ->reject(fn (ProductImage $image) => ! empty($rows[$image->id]['delete']))
            ->each(fn (ProductImage $image) => $image->sort_order = (int) ($rows[$image->id]['sort_order'] ?? $image->sort_order))
            ->sortBy('sort_order')
            ->values();

        foreach (array_values((array) $request->input('preview_new', [])) as $i => $type) {
            $media->push(new ProductImage(['type' => $type === 'video' ? 'video' : 'image', 'path' => "__pending__/{$i}", 'sort_order' => PHP_INT_MAX]));
        }

        $product->setRelation('images', $media);
        $product->setRelation('item', $product->item_id ? Item::find($product->item_id) : null);

        return view('pages.products.show', [
            'product' => $product,
            'others' => Product::with(['images', 'item'])->active()->ordered()->whereKeyNot($product->id)->limit(6)->get(),
        ]);
    }

    /**
     * Buat produk website dari satu knalpot di stok. Harga awal = harga jual
     * online; admin bisa mengubahnya di halaman berikutnya.
     */
    public function storeFromStock(Item $item): RedirectResponse
    {
        if ($existing = Product::where('item_id', $item->id)->first()) {
            return redirect()->route('admin.web-products.edit', $existing)->with('status', 'Produk ini sudah ada di website.');
        }

        $slug = Str::slug($item->name);
        $slug = Product::where('slug', $slug)->exists() ? $slug.'-'.$item->id : $slug;

        $product = Product::create([
            'item_id' => $item->id,
            'name' => $item->name,
            'slug' => $slug,
            'fitment' => $item->model_motor,
            'price' => $item->sell_price_online,
            'price_follows_item' => true,
            'currency' => 'IDR',
            'description' => collect([
                'Model motor' => $item->model_motor,
                'Finishing' => $item->finishing,
                'Tipe' => $item->variant,
                'Ukuran' => $item->size,
                'Merek' => $item->brand,
            ])->filter()->map(fn ($value, $label) => '<p><strong>'.e($label).':</strong> '.e($value).'</p>')->implode(''),
            'is_active' => false,
            'sort_order' => (int) Product::max('sort_order') + 1,
        ]);

        return redirect()->route('admin.web-products.edit', $product)
            ->with('status', 'Produk dibuat dari stok sebagai draf. Atur harga, foto & video, cek pratinjau, lalu klik "Posting ke website".');
    }

    public function store(Request $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($this->validated($request));
            $this->syncImages($request, $product);

            return $product;
        });

        return redirect()->route('admin.web-products.edit', $product)->with('status', $product->is_active ? 'Produk diposting ke website.' : 'Draf disimpan — belum tampil di website.');
    }

    public function edit(Product $webProduct): View
    {
        return view('admin.website.products.form', [
            'product' => $webProduct->load(['images', 'item']),
            'items' => Item::where('is_active', true)->orderBy('sku')->get(),
        ]);
    }

    public function update(Request $request, Product $webProduct): RedirectResponse
    {
        DB::transaction(function () use ($request, $webProduct) {
            $webProduct->update($this->validated($request, $webProduct));
            $this->syncImages($request, $webProduct);
        });

        return redirect()->route('admin.web-products.edit', $webProduct)->with('status', $webProduct->refresh()->is_active ? 'Disimpan — tayang di website.' : 'Draf disimpan — belum tampil di website.');
    }

    public function destroy(Product $webProduct): RedirectResponse
    {
        $webProduct->images->each(fn ($image) => $this->deleteSiteUpload($image->path));
        $webProduct->delete();

        return redirect()->route('admin.web-products.index')->with('status', 'Produk website dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product)],
            'fitment' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::in(['IDR', 'USD'])],
            'item_id' => ['nullable', 'exists:items,id', Rule::unique('products', 'item_id')->ignore($product)],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'images' => ['array'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'new_images' => ['array'],
            'new_images.*' => ['image', 'max:5120'],
            'new_media' => ['array', 'max:20'],
            'new_media.*' => ['file', 'mimetypes:'.implode(',', [...self::PHOTO_TYPES, ...self::VIDEO_TYPES]), function (string $attribute, $file, \Closure $fail) {
                $isVideo = in_array($file->getMimeType(), self::VIDEO_TYPES, true);
                $limitKb = $isVideo ? self::VIDEO_MAX_KB : self::PHOTO_MAX_KB;

                if ($file->getSize() > $limitKb * 1024) {
                    $fail(($isVideo ? 'Video' : 'Foto').' "'.$file->getClientOriginalName().'" melebihi '.($limitKb / 1024).' MB.');
                }
            }],
        ], [
            'new_media.*.mimetypes' => 'Format tidak didukung. Pakai foto JPG/PNG/WebP atau video MP4/WebM/MOV.',
            'new_media.*.uploaded' => 'Berkas gagal diunggah — kemungkinan terlalu besar.',
        ]);

        unset($data['images'], $data['new_images'], $data['new_media']);
        // Tombol "Simpan draf" / "Posting ke website" menentukan status tayang.
        $data['is_active'] = $request->has('publish') ? $request->boolean('publish') : $request->boolean('is_active');
        $data['price_follows_item'] = $request->boolean('price_follows_item') && filled($data['item_id'] ?? null);

        return $data;
    }

    /** Urutan, hapus, dan tambah foto/video galeri dalam satu kali simpan. */
    private function syncImages(Request $request, Product $product): void
    {
        foreach ($request->input('images', []) as $id => $row) {
            $image = $product->images()->find($id);

            if (! $image) {
                continue;
            }

            if (! empty($row['delete'])) {
                $this->deleteSiteUpload($image->path);
                $image->delete();

                continue;
            }

            $image->update(['sort_order' => (int) ($row['sort_order'] ?? $image->sort_order)]);
        }

        $next = (int) $product->images()->max('sort_order') + 1;

        foreach ([...$request->file('new_media', []), ...$request->file('new_images', [])] as $file) {
            $isVideo = in_array($file->getMimeType(), self::VIDEO_TYPES, true);

            $product->images()->create([
                'type' => $isVideo ? 'video' : 'image',
                'path' => $this->storeSiteUpload($file, $isVideo ? 'products/videos' : 'products'),
                'sort_order' => $next++,
            ]);
        }
    }
}
