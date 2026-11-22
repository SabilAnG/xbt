@extends('layouts.admin', ['title' => 'Upload produk dari stok', 'subtitle' => 'Pilih knalpot dari Stok Produk untuk ditampilkan di website. Pembeli memesan lewat WhatsApp.'])

@section('actions')
    <a href="{{ route('admin.web-products.index') }}" class="btn">Kembali ke katalog</a>
@endsection

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama knalpot">
        </div>
        <label class="flex items-center gap-2 pb-2 text-sm text-zinc-700">
            <input type="checkbox" name="available" value="1" class="rounded border-zinc-300" @checked(request('available'))> Hanya yang ada stok
        </label>
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>SKU</th><th>Knalpot</th><th class="num">Sisa stok</th>
                    <th class="num">Harga modal (HPP)</th><th class="num">Harga jual online</th><th class="num">Harga offline</th>
                    <th>Di website</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($items as $item)
                    @php $product = $published->get($item->id); @endphp
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap text-zinc-500">{{ $item->sku }}</td>
                        <td class="min-w-56">{{ $item->name }}</td>
                        <td class="num font-semibold {{ $item->stock <= 0 ? 'text-red-600' : '' }}">{{ qty($item->stock) }}</td>
                        <td class="num">{{ rupiah($item->cost_price) }}</td>
                        <td class="num">{{ rupiah($item->sell_price_online) }}</td>
                        <td class="num text-zinc-500">{{ rupiah($item->sell_price) }}</td>
                        <td class="whitespace-nowrap">
                            @if ($product)
                                <x-admin.status :value="$product->is_active ? 'AKTIF' : 'NONAKTIF'" />
                                <span class="ml-1 text-xs tabular-nums text-zinc-600">{{ $product->formattedPrice() }}</span>
                            @else
                                <span class="text-xs text-zinc-400">belum</span>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @if ($product)
                                <a href="{{ route('admin.web-products.edit', $product) }}" class="btn btn-sm">Atur</a>
                            @else
                                <form method="POST" action="{{ route('admin.web-products.from-stock.store', $item) }}" class="inline">
                                    @csrf
                                    <button class="btn btn-primary btn-sm">Upload ke website</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-zinc-500">Tidak ada knalpot.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
