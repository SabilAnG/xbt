@extends('layouts.admin', ['title' => 'Master Item', 'subtitle' => 'Semua barang dalam satu daftar: bahan baku, pipa, setengah jadi, aksesoris, dan produk jadi. Harga & stok dalam satuan pakai; pembelian dalam satuan beli.'])

@php
    $types = \App\Http\Controllers\Admin\MasterItemController::TYPES;
    $createLinks = [
        'bahan-baku' => route('admin.materials.create'),
        'pipa' => route('admin.materials.create', ['jenis' => 'pipa']),
        'setengah-jadi' => route('admin.materials.create', ['gudang' => 'setengah-jadi']),
        'aksesoris' => route('admin.materials.create', ['gudang' => 'aksesoris']),
        'produk-jadi' => route('admin.items.create'),
    ];
@endphp

@section('actions')
    <a href="{{ route('admin.units.index') }}" class="btn hidden sm:inline-flex">Master Satuan</a>
    <details class="relative">
        <summary class="btn btn-primary cursor-pointer list-none">+ Item</summary>
        <div class="card absolute right-0 z-20 mt-1 w-56 overflow-hidden p-1 shadow-lg">
            @foreach ($types as $key => $label)
                <a href="{{ $createLinks[$key] }}" class="block rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ $label }}</a>
            @endforeach
        </div>
    </details>
@endsection

@section('content')
    <nav class="flex flex-wrap gap-2">
        <a href="{{ route('admin.master-items.index', request()->only('q')) }}" @class(['btn btn-sm', 'btn-primary' => ! $type])>Semua <span class="opacity-70">{{ $total }}</span></a>
        @foreach ($types as $key => $label)
            <a href="{{ route('admin.master-items.index', ['jenis' => $key] + request()->only('q')) }}" @class(['btn btn-sm', 'btn-primary' => $type === $key])>{{ $label }} <span class="opacity-70">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
    </nav>

    <x-admin.filters>
        @if ($type)<input type="hidden" name="jenis" value="{{ $type }}">@endif
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Kode, nama, atau ukuran">
        </div>
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr><th>Kode</th><th>Item</th><th>Jenis</th><th>Gudang</th><th>Satuan</th><th class="num">Harga</th><th class="num">Stok</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr @class(['opacity-50' => ! $row['active']])>
                        <td class="font-mono text-xs whitespace-nowrap text-slate-500">{{ $row['code'] }}</td>
                        <td class="min-w-52">
                            <a href="{{ $row['show'] }}" class="link">{{ $row['name'] }}</a>
                            @if ($row['spec'] || $row['category'])<p class="text-xs text-slate-500">{{ collect([$row['spec'], $row['category']])->filter()->implode(' · ') }}</p>@endif
                        </td>
                        <td class="text-xs whitespace-nowrap">{{ $types[$row['type']] }}</td>
                        <td class="text-xs whitespace-nowrap text-slate-500">{{ $row['warehouse'] }}</td>
                        <td class="text-xs whitespace-nowrap">
                            {{ $row['units'] }}
                            @if ($row['conversion'])<p class="text-slate-400">{{ $row['conversion'] }}</p>@endif
                        </td>
                        <td class="num whitespace-nowrap">{{ rupiah($row['price']) }}<span class="text-xs text-slate-400">/{{ $row['price_unit'] }}</span></td>
                        <td class="num whitespace-nowrap"><span class="font-semibold">{{ qty($row['stock'], 2) }}</span> <span class="text-xs text-slate-400">{{ $row['stock_unit'] }}</span></td>
                        <td><x-admin.status :value="$row['status']" /></td>
                        <td class="text-right whitespace-nowrap"><a href="{{ $row['edit'] }}" class="btn btn-sm">Ubah</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-10 text-center text-slate-500">Belum ada item. Klik <strong>+ Item</strong> untuk menambah.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
