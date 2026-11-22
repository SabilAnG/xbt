@extends('layouts.admin', ['title' => $warehouse->name, 'subtitle' => $warehouse->description ?: 'Gudang '.$warehouse->typeLabel()])

@section('actions')
    <a href="{{ route('admin.warehouses.index') }}" class="btn">Semua gudang</a>
    <a href="{{ route('admin.warehouses.edit', $warehouse->slug()) }}" class="btn">Ubah info</a>
    @if ($warehouse->type === 'bahan_baku')
        <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary">+ Pembelian bahan</a>
    @elseif ($warehouse->type === 'setengah_jadi')
        <a href="{{ route('admin.warehouses.movements.create') }}" class="btn btn-primary">+ Catat mutasi komponen</a>
    @else
        <a href="{{ route('admin.productions.create') }}" class="btn btn-primary">+ Produksi</a>
    @endif
@endsection

@section('content')
    <nav class="flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 text-sm">
        @foreach (\App\Models\Warehouse::TYPES as $key => [$slug, $label])
            <a href="{{ route('admin.warehouses.show', $slug) }}" @class([
                'flex-1 rounded-lg px-3 py-2 text-center font-medium whitespace-nowrap transition',
                'bg-slate-900 text-white' => $warehouse->type === $key,
                'text-slate-600 hover:bg-slate-100' => $warehouse->type !== $key,
            ])>{{ $loop->iteration }}. {{ $label }}</a>
        @endforeach
    </nav>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-admin.stat label="Nilai stok" :value="rupiah($summary['value'])" tone="brand" />
        <x-admin.stat label="Jenis barang aktif" :value="$summary['count']" />
        <x-admin.stat label="Total qty" :value="qty(round($summary['qty'], 2))" />
        <x-admin.stat label="Habis / menipis" :value="$summary['low']" :tone="$summary['low'] > 0 ? 'danger' : 'success'" />
    </section>

    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Kode / SKU atau nama">
        </div>
        @if ($warehouse->type === 'bahan_baku')
            <x-admin.select name="category" label="Kategori" :options="$categories" :value="request('category')" placeholder="Semua" />
        @elseif ($warehouse->type === 'setengah_jadi')
            <x-admin.select name="group" label="Kelompok" :options="\App\Models\Component::GROUPS" :value="request('group')" placeholder="Semua" />
        @endif
        <x-admin.select name="status" label="Status" :options="['HABIS' => 'HABIS', 'MENIPIS' => 'MENIPIS', 'AMAN' => 'AMAN']" :value="request('status')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>{{ $warehouse->type === 'setengah_jadi' ? 'Kode' : 'SKU' }}</th>
                    <th>Nama</th>
                    <th>{{ match ($warehouse->type) { 'bahan_baku' => 'Kategori', 'setengah_jadi' => 'Kelompok', default => 'Merek' } }}</th>
                    <th class="num">Stok</th>
                    <th class="num">Min.</th>
                    <th>Status</th>
                    <th class="num">{{ $warehouse->type === 'setengah_jadi' ? 'Biaya bahan / unit' : 'HPP / unit' }}</th>
                    <th class="num">Nilai stok</th>
                    @if ($warehouse->type === 'setengah_jadi')<th></th>@endif
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    @php
                        [$code, $link, $group, $unitCost] = match ($warehouse->type) {
                            'bahan_baku' => [$row->sku, route('admin.materials.show', $row), $row->category?->name, (float) $row->cost_price],
                            'setengah_jadi' => [$row->code, route('admin.components.show', $row), $row->groupLabel(), $row->materialCost()],
                            default => [$row->sku, route('admin.items.show', $row), $row->brand, (float) $row->cost_price],
                        };
                        $stock = round((float) $row->stock, 2);
                    @endphp
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap text-slate-500">{{ $code }}</td>
                        <td class="min-w-52"><a href="{{ $link }}" class="link">{{ $row->name }}</a></td>
                        <td class="text-xs whitespace-nowrap">{{ $group }}</td>
                        <td @class(['num font-semibold', 'text-rose-600' => $stock < 0])>{{ qty($stock) }} <span class="text-xs font-normal text-slate-400">{{ $row->unit }}</span></td>
                        <td class="num text-slate-500">{{ qty((float) $row->min_stock) }}</td>
                        <td><x-admin.status :value="$row->stockStatus()" /></td>
                        <td class="num">{{ rupiah($unitCost) }}</td>
                        <td class="num">{{ rupiah(max($stock, 0) * $unitCost) }}</td>
                        @if ($warehouse->type === 'setengah_jadi')
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.warehouses.movements.create', ['component' => $row->id, 'type' => 'masuk']) }}" class="btn btn-sm">+ Masuk</a>
                                <a href="{{ route('admin.warehouses.movements.create', ['component' => $row->id, 'type' => 'keluar']) }}" class="btn btn-sm">− Keluar</a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-10 text-center text-slate-500">
                        @if ($warehouse->type === 'setengah_jadi' && ! request()->hasAny(['q', 'group', 'status']))
                            Belum ada komponen. Daftarkan dulu di <a href="{{ route('admin.components.create') }}" class="link">Master Komponen</a>.
                        @else
                            Tidak ada barang yang cocok.
                        @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($warehouse->type === 'setengah_jadi')
        @include('admin.warehouses.partials.movements', ['movements' => $recent, 'title' => 'Mutasi terakhir', 'showComponent' => true])
    @endif
@endsection
