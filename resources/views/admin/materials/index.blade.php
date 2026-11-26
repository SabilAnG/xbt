@extends('layouts.admin', ['title' => 'Master Bahan', 'subtitle' => 'Semua barang yang dipakai formula: bahan mentah di Gudang Bahan Baku (pipa, plat, baut) dan barang beli jadi di Gudang Setengah Jadi (pureng, plendes). Harga ikut terbawa ke formula.'])

@section('actions')
    <a href="{{ route('admin.materials.create', ['jenis' => 'pipa']) }}" class="btn hidden sm:inline-flex">+ Pipa</a>
    <a href="{{ route('admin.materials.create', ['gudang' => 'aksesoris']) }}" class="btn hidden sm:inline-flex">+ Aksesoris</a>
    <a href="{{ route('admin.materials.create') }}" class="btn btn-primary">+ Bahan</a>
@endsection

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Kode, nama, atau ukuran">
        </div>
        <x-admin.select name="warehouse" label="Gudang" :options="$warehouses->pluck('name', 'id')" :value="request('warehouse')" placeholder="Semua" />
        <x-admin.select name="category" label="Kategori" :options="$categories" :value="request('category')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Kode</th>
                    <th>Bahan</th>
                    <th>Gudang asal</th>
                    <th>Kategori</th>
                    <th class="num">Harga</th>
                    <th class="num">Stok</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($materials as $material)
                    <tr @class(['opacity-50' => ! $material->is_active])>
                        <td class="font-mono text-xs whitespace-nowrap text-slate-500">{{ $material->code }}</td>
                        <td class="min-w-52">
                            <a href="{{ route('admin.materials.show', $material) }}" class="link">{{ $material->name }}</a>
                            @if ($material->spec)<p class="text-xs text-slate-500">{{ $material->spec }}</p>@endif
                        </td>
                        <td class="text-xs whitespace-nowrap">{{ $material->warehouse?->typeLabel() }}</td>
                        <td class="text-xs whitespace-nowrap text-slate-500">{{ $material->category?->name ?? '—' }}</td>
                        <td class="num whitespace-nowrap">
                            @if ($material->isPipe())
                                {{ rupiah($material->unitCost()) }}<span class="text-xs text-slate-400">/cm</span>
                                <p class="text-xs text-slate-400">{{ rupiah($material->purchaseUnitCost()) }}/batang {{ qty($material->bar_length_cm, 2) }} cm</p>
                            @else
                                {{ rupiah($material->unitCost()) }}<span class="text-xs text-slate-400">/{{ $material->stockUnit() }}</span>
                                @if ($material->conversionLabel())<p class="text-xs text-slate-400">{{ $material->conversionLabel() }}</p>@endif
                            @endif
                        </td>
                        <td class="num whitespace-nowrap">
                            @if ($material->isPipe())
                                <span class="font-semibold">{{ qty($material->stock, 2) }}</span> <span class="text-xs text-slate-400">cm</span>
                                @foreach ($material->stocks->where('qty', '>', 0) as $stock)
                                    <p class="text-xs text-slate-500">{{ $stock->warehouse->typeLabel() }}: {{ qty($stock->qty, 2) }} cm</p>
                                @endforeach
                            @else
                                <span class="font-semibold">{{ qty($material->stock) }}</span> <span class="text-xs text-slate-400">{{ $material->stockUnit() }}</span>
                            @endif
                        </td>
                        <td><x-admin.status :value="$material->is_active ? $material->stockStatus() : 'NONAKTIF'" /></td>
                        <td class="text-right whitespace-nowrap"><a href="{{ route('admin.materials.edit', $material) }}" class="btn btn-sm">Ubah</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-slate-500">
                        Belum ada bahan. Mulai dengan <a href="{{ route('admin.materials.create', ['jenis' => 'pipa']) }}" class="link">menambah pipa</a>
                        atau <a href="{{ route('admin.materials.create', ['gudang' => 'setengah-jadi']) }}" class="link">barang setengah jadi</a> seperti pureng & plendes.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
