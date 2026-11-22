@extends('layouts.admin', ['title' => 'Master Komponen', 'subtitle' => 'Bagian-bagian knalpot (leher, tabung silencer, sarangan, moncong, …) dan bahan baku penyusunnya. Stoknya ada di Gudang Setengah Jadi.'])

@section('actions')
    <a href="{{ route('admin.warehouses.show', 'setengah-jadi') }}" class="btn hidden sm:inline-flex">Gudang Setengah Jadi</a>
    <a href="{{ route('admin.components.create') }}" class="btn btn-primary">+ Komponen</a>
@endsection

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Kode atau nama komponen">
        </div>
        <x-admin.select name="group" label="Kelompok" :options="\App\Models\Component::GROUPS" :value="request('group')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama komponen</th>
                    <th>Kelompok</th>
                    <th>Bahan baku penyusun</th>
                    <th class="num">Biaya bahan / unit</th>
                    <th class="num">Stok</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($parts as $part)
                    <tr @class(['opacity-50' => ! $part->is_active])>
                        <td class="font-mono text-xs whitespace-nowrap text-slate-500">{{ $part->code }}</td>
                        <td class="min-w-48"><a href="{{ route('admin.components.show', $part) }}" class="link">{{ $part->name }}</a></td>
                        <td class="text-xs whitespace-nowrap">{{ $part->groupLabel() }}</td>
                        <td class="min-w-56 text-xs text-slate-600">
                            @forelse ($part->materials as $line)
                                <span class="whitespace-nowrap">{{ $line->material?->name }} <span class="text-slate-400">× {{ qty((float) $line->qty, 4) }}</span></span>@unless ($loop->last)<span class="text-slate-300"> · </span>@endunless
                            @empty
                                <span class="text-amber-600">Belum diisi</span>
                            @endforelse
                        </td>
                        <td class="num">{{ $part->materials->isNotEmpty() ? rupiah($part->materialCost()) : '—' }}</td>
                        <td class="num font-semibold">{{ qty((float) $part->stock) }} <span class="text-xs font-normal text-slate-400">{{ $part->unit }}</span></td>
                        <td><x-admin.status :value="$part->is_active ? $part->stockStatus() : 'NONAKTIF'" /></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.components.edit', $part) }}" class="btn btn-sm">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-slate-500">
                        Belum ada komponen. <a href="{{ route('admin.components.create') }}" class="link">Tambah komponen pertama</a>.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
