@extends('layouts.admin', ['title' => 'Stok Produk', 'subtitle' => 'Knalpot jadi — stok bertambah dari Produksi dan berkurang dari Penjualan'])

@section('actions')
    <a href="{{ route('admin.items.create') }}" class="btn btn-primary">+ Produk</a>
@endsection

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama produk atau SKU">
        </div>
        <x-admin.select name="brand" label="Merek" :options="$brands->mapWithKeys(fn ($b) => [$b => $b])" :value="request('brand')" placeholder="Semua" />
        <x-admin.select name="status" label="Status" :options="['HABIS' => 'HABIS', 'MENIPIS' => 'MENIPIS', 'AMAN' => 'AMAN']" :value="request('status')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama produk</th>
                    <th>Resep</th>
                    <th class="num">HPP</th>
                    <th class="num">Harga offline</th>
                    <th class="num">Harga online</th>
                    <th class="num">Stok awal</th>
                    <th class="num">+ Produksi</th>
                    <th class="num">− Terjual</th>
                    <th class="num">Sisa stok</th>
                    <th class="num">Min.</th>
                    <th>Status</th>
                    <th class="num">Nilai stok</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($items as $item)
                    <tr @class(['opacity-50' => ! $item->is_active])>
                        <td class="font-mono text-xs whitespace-nowrap text-zinc-500">{{ $item->sku }}</td>
                        <td>
                            <a href="{{ route('admin.items.show', $item) }}" class="link">{{ $item->name }}</a>
                            <p class="text-xs text-zinc-500">{{ collect([$item->model_motor, $item->finishing, $item->variant, $item->size])->filter()->implode(' · ') }}</p>
                        </td>
                        <td class="whitespace-nowrap text-xs">{{ $item->formula?->name ?? '—' }}</td>
                        <td class="num">{{ rupiah($item->cost_price) }}</td>
                        <td class="num">{{ rupiah($item->sell_price) }}</td>
                        <td class="num">{{ rupiah($item->sell_price_online) }}</td>
                        <td class="num text-zinc-500">{{ qty($item->opening_stock) }}</td>
                        <td class="num">{{ qty($flow->get($item->id)?->produced ?? 0) }}</td>
                        <td class="num">{{ qty($flow->get($item->id)?->sold ?? 0) }}</td>
                        <td class="num font-semibold {{ $item->stock < 0 ? 'text-red-600' : '' }}">{{ qty($item->stock) }}</td>
                        <td class="num text-zinc-500">{{ qty($item->min_stock) }}</td>
                        <td><x-admin.status :value="$item->stockStatus()" /></td>
                        <td class="num">{{ rupiah($item->stockValue()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="py-8 text-center text-zinc-500">Tidak ada produk.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $items->links() }}
@endsection
