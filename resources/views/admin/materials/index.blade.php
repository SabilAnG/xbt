@extends('layouts.admin', ['title' => 'Stok Bahan', 'subtitle' => 'Sisa = pembelian − terpakai produksi (resep × qty) ± penyesuaian. Minus = cek fisik lalu koreksi.'])

@section('actions')
    <a href="{{ route('admin.adjustments.create') }}" class="btn hidden sm:inline-flex">± Penyesuaian</a>
    <a href="{{ route('admin.materials.create') }}" class="btn btn-primary">+ Bahan</a>
@endsection

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama bahan">
        </div>
        <x-admin.select name="category" label="Kategori" :options="$categories->pluck('name', 'id')" :value="request('category')" placeholder="Semua" />
        <x-admin.select name="status" label="Status" :options="['HABIS' => 'HABIS', 'MENIPIS' => 'MENIPIS', 'AMAN' => 'AMAN']" :value="request('status')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama bahan</th>
                    <th>Kategori</th>
                    <th>Satuan</th>
                    <th class="num">Stok awal</th>
                    <th class="num">+ Masuk</th>
                    <th class="num">− Terpakai</th>
                    <th class="num">± Penyesuaian</th>
                    <th class="num">Sisa stok</th>
                    <th class="num">Min.</th>
                    <th>Status</th>
                    <th class="num">Harga rata-rata</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($materials as $material)
                    @php $row = $flow->get($material->id); @endphp
                    <tr @class(['opacity-50' => ! $material->is_active])>
                        <td class="font-mono text-xs whitespace-nowrap text-slate-500">{{ $material->sku }}</td>
                        <td class="min-w-52"><a href="{{ route('admin.materials.show', $material) }}" class="link">{{ $material->name }}</a></td>
                        <td class="text-xs whitespace-nowrap">{{ $material->category?->name }}</td>
                        <td>{{ $material->unit }}</td>
                        <td class="num text-slate-500">{{ qty($material->opening_stock) }}</td>
                        <td class="num">{{ qty($row->masuk ?? 0) }}</td>
                        <td class="num">{{ qty($row->terpakai ?? 0) }}</td>
                        <td class="num">{{ qty($row->penyesuaian ?? 0) }}</td>
                        <td class="num font-semibold {{ $material->stock < 0 ? 'text-rose-600' : '' }}">{{ qty(round((float) $material->stock, 2)) }}</td>
                        <td class="num text-slate-500">{{ qty($material->min_stock) }}</td>
                        <td><x-admin.status :value="$material->stockStatus()" /></td>
                        <td class="num">{{ rupiah($material->cost_price) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="py-8 text-center text-slate-500">Tidak ada bahan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
