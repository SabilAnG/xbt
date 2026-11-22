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
                    <th class="num">Sisa stok</th>
                    <th>Status</th>
                    <th class="num">Min.</th>
                    <th class="num">Awal</th>
                    <th class="num">+ Produksi</th>
                    <th class="num">− Terjual</th>
                    <th class="num">HPP</th>
                    <th class="num">Harga online</th>
                    <th class="num">Harga offline</th>
                    <th class="num">Nilai stok</th>
                    <th>Resep</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($items as $item)
                    <tr @class(['opacity-50' => ! $item->is_active])>
                        <td class="font-mono text-xs whitespace-nowrap text-slate-500">{{ $item->sku }}</td>
                        <td class="min-w-64">
                            <a href="{{ route('admin.items.show', $item) }}" class="link">{{ $item->name }}</a>
                        </td>
                        <td class="num text-[15px] font-semibold {{ $item->stock < 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ qty($item->stock) }}</td>
                        <td><x-admin.status :value="$item->stockStatus()" /></td>
                        <td class="num text-slate-500">{{ qty($item->min_stock) }}</td>
                        <td class="num text-slate-500">{{ qty($item->opening_stock) }}</td>
                        <td class="num text-emerald-700">{{ qty($flow->get($item->id)?->produced ?? 0) }}</td>
                        <td class="num">{{ qty($flow->get($item->id)?->sold ?? 0) }}</td>
                        <td class="num">{{ rupiah($item->cost_price) }}</td>
                        <td class="num">{{ rupiah($item->sell_price_online) }}</td>
                        <td class="num text-slate-500">{{ rupiah($item->sell_price) }}</td>
                        <td class="num font-medium">{{ rupiah($item->stockValue()) }}</td>
                        <td class="text-xs whitespace-nowrap text-slate-500">{{ $item->formula?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="py-8 text-center text-slate-500">Tidak ada produk.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $items->links() }}
@endsection
