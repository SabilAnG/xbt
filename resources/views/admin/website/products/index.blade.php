@extends('layouts.admin', ['title' => 'Katalog Produk', 'subtitle' => 'Produk yang tampil di halaman Products website'])

@section('actions')
    <a href="{{ route('products.index') }}" target="_blank" class="btn hidden sm:inline-flex">Lihat halaman ↗</a>
    <a href="{{ route('admin.web-products.create') }}" class="btn">+ Produk manual</a>
    <a href="{{ route('admin.web-products.from-stock') }}" class="btn btn-primary">⇪ Upload dari stok</a>
@endsection

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama produk">
        </div>
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th class="w-20">Foto</th><th>Produk</th><th>Cocok untuk</th><th>Stok knalpot</th><th class="num">HPP</th><th class="num">Harga website</th><th class="num">Urutan</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td>
                            @if ($image = $product->cardImage())
                                <img src="{{ asset($image->thumb()) }}" alt="" class="size-12 rounded-md border border-zinc-200 object-cover">
                            @else
                                <div class="size-12 rounded-md bg-zinc-100"></div>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.web-products.edit', $product) }}" class="link">{{ $product->name }}</a>
                            <p class="font-mono text-[11px] text-zinc-400">/products/{{ $product->slug }}</p>
                        </td>
                        <td class="text-xs">{{ $product->fitment }}</td>
                        <td class="text-xs whitespace-nowrap">
                            @if ($product->item)
                                <a href="{{ route('admin.items.show', $product->item) }}" class="link">{{ $product->item->sku }}</a> · {{ qty($product->item->stock) }} unit
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="num text-zinc-500">{{ $product->item ? rupiah($product->item->cost_price) : '—' }}</td>
                        <td class="num font-semibold">{{ $product->formattedPrice() }}</td>
                        <td class="num">{{ $product->sort_order }}</td>
                        <td><x-admin.status :value="$product->is_active ? 'AKTIF' : 'NONAKTIF'" /></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('products.show', $product->slug) }}" target="_blank" class="btn btn-sm">Lihat ↗</a>
                            <a href="{{ route('admin.web-products.edit', $product) }}" class="btn btn-sm">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-zinc-500">Belum ada produk.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $products->links() }}
@endsection
