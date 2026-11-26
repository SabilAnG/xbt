@extends('layouts.admin', ['title' => $warehouse->name, 'subtitle' => 'Knalpot jadi hasil produksi. HPP = rata-rata dari produksi yang sudah dibukukan; penjualan mengurangi stok di sini.'])

@section('actions')
    <a href="{{ route('admin.warehouses.index') }}" class="btn">Semua gudang</a>
    <a href="{{ route('admin.sales.create') }}" class="btn btn-primary">+ Penjualan</a>
@endsection

@section('content')
    <section class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-admin.stat label="Total stok" :value="qty($items->sum('stock')).' pcs'" />
        <x-admin.stat label="Nilai stok (HPP)" :value="rupiah($items->sum(fn ($item) => $item->stockValue()))" tone="brand" />
    </section>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Produk</th><th>Formula</th><th class="num">Stok</th><th class="num">HPP</th><th class="num">Harga jual</th><th class="num">Nilai</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td><a href="{{ route('admin.items.show', $item) }}" class="link">{{ $item->name }}</a></td>
                        <td class="text-xs text-slate-500">{{ $item->formula?->name ?? '—' }}</td>
                        <td class="num font-semibold">{{ qty($item->stock) }}</td>
                        <td class="num">{{ rupiah($item->cost_price) }}</td>
                        <td class="num text-slate-500">{{ rupiah($item->sell_price) }}</td>
                        <td class="num">{{ rupiah($item->stockValue()) }}</td>
                        <td><x-admin.status :value="$item->stockStatus()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">Belum ada produk jadi. Produk dibuat otomatis saat formula disimpan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
