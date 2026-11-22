@extends('layouts.admin', ['title' => $product->exists ? 'Ubah produk website' : 'Produk website baru', 'subtitle' => $product->exists ? $product->name : 'Tampil di halaman Products'])

@section('actions')
    <a href="{{ route('admin.web-products.index') }}" class="btn">Kembali</a>
    @if ($product->exists)
        <a href="{{ route('products.show', $product->slug) }}" target="_blank" class="btn">Lihat ↗</a>
    @endif
@endsection

@section('content')
    <form method="POST" action="{{ $product->exists ? route('admin.web-products.update', $product) : route('admin.web-products.store') }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        @if ($stockItem = $product->item)
            @php
                $hpp = (float) $stockItem->cost_price;
                $webPrice = (float) old('price', $product->price);
                $margin = $product->currency === 'IDR' ? $webPrice - $hpp : null;
            @endphp
            <section class="card overflow-hidden">
                <div class="card-header bg-brand-50">
                    <h2 class="card-title">Tertaut ke Stok Produk: <a href="{{ route('admin.items.show', $stockItem) }}" class="link">{{ $stockItem->sku }}</a></h2>
                    <x-admin.status :value="$stockItem->stockStatus()" />
                </div>
                <dl class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-5">
                    @foreach ([
                        'Harga modal (HPP)' => rupiah($hpp),
                        'Harga jual online' => rupiah($stockItem->sell_price_online),
                        'Harga jual offline' => rupiah($stockItem->sell_price),
                        'Sisa stok' => qty($stockItem->stock).' unit',
                        'Untung per unit (harga web − HPP)' => $margin === null ? '—' : rupiah($margin).($hpp > 0 ? ' ('.round($margin / $hpp * 100).'%)' : ''),
                    ] as $label => $value)
                        <div class="bg-white px-4 py-3">
                            <dt class="text-xs text-slate-500">{{ $label }}</dt>
                            <dd @class(['mt-1 text-sm font-semibold tabular-nums', 'text-rose-600' => $margin !== null && $margin < 0 && $loop->last])>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="px-5 py-3 text-xs text-slate-500">Harga modal dan harga jual hanya terlihat di admin. Website menampilkan <strong>Harga website</strong> di bawah.</p>
            </section>
        @endif

        <section class="card grid gap-4 p-6 sm:grid-cols-2">
            <x-admin.field name="name" label="Nama produk" :value="$product->name" required class="sm:col-span-2" />
            <x-admin.field name="slug" label="Slug URL" :value="$product->slug" hint="Kosongkan untuk dibuat otomatis dari nama." />
            <x-admin.field name="fitment" label="Cocok untuk (fitment)" :value="$product->fitment" />
            <x-admin.select name="item_id" label="Tautan ke Stok Produk" :value="$product->item_id" placeholder="— tidak ditautkan —"
                :options="$items->mapWithKeys(fn ($item) => [$item->id => $item->sku.' · '.$item->name.' (stok '.qty($item->stock).')'])"
                hint="Bila ditautkan, website menampilkan status stok dan admin melihat harga modal." />
            <div class="grid grid-cols-3 gap-3">
                <x-admin.field name="price" label="Harga website" type="number" step="any" min="0" :value="(float) $product->price" required class="col-span-2"
                    :hint="$product->item ? 'Harga jual online stok: '.rupiah($product->item->sell_price_online) : 'Harga yang tampil di halaman produk.'" />
                <x-admin.select name="currency" label="Mata uang" :options="['IDR' => 'Rp (IDR)', 'USD' => '$ (USD)']" :value="$product->currency ?? 'IDR'" :placeholder="false" required />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-admin.field name="sort_order" label="Urutan" type="number" min="0" :value="$product->sort_order" required />
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $product->is_active))> Tampilkan
                    </label>
                </div>
            </div>
            <x-admin.field name="description" label="Deskripsi (HTML)" type="textarea" rows="10" :value="$product->description" class="sm:col-span-2"
                hint="Boleh memakai tag HTML seperti <p>, <ul><li>, <strong>." />
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Galeri foto</h2>
                <p class="text-xs text-slate-500">Foto dengan urutan terkecil jadi foto utama.</p>
            </div>
            <div class="space-y-5 p-5">
                @if ($product->exists && $product->images->isNotEmpty())
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($product->images as $image)
                            <div class="rounded-lg border border-slate-200 p-2">
                                <img src="{{ asset($image->thumb()) }}" alt="" class="aspect-square w-full rounded-md bg-slate-100 object-cover">
                                <div class="mt-2 flex items-center gap-2">
                                    <input type="number" min="0" name="images[{{ $image->id }}][sort_order]" value="{{ $image->sort_order }}" class="input w-20 py-1 text-xs" aria-label="Urutan">
                                    <label class="ml-auto flex items-center gap-1 text-xs text-rose-600">
                                        <input type="checkbox" name="images[{{ $image->id }}][delete]" value="1" class="rounded border-slate-300"> Hapus
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div>
                    <label class="label" for="new_images">Tambah foto</label>
                    <input id="new_images" name="new_images[]" type="file" accept="image/*" multiple class="input">
                    <p class="hint">Bisa pilih beberapa foto sekaligus. Maks 5 MB per foto.</p>
                    @error('new_images.*')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <div class="flex items-center justify-between gap-2">
            <div>
                @if ($product->exists)
                    <button form="delete-product" class="btn btn-danger">Hapus produk</button>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.web-products.index') }}" class="btn">Batal</a>
                <button class="btn btn-primary">Simpan</button>
            </div>
        </div>
    </form>

    @if ($product->exists)
        <form id="delete-product" method="POST" action="{{ route('admin.web-products.destroy', $product) }}" onsubmit="return confirm('Hapus produk ini beserta fotonya?')">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection
