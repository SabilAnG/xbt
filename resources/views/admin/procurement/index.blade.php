@extends('layouts.admin', ['title' => 'Riwayat Pengadaan', 'subtitle' => 'Arsip 237 transaksi bahan dari Master Pengadaan — acuan harga, tidak dihitung ulang di Ringkasan'])

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama barang atau fungsi">
        </div>
        <x-admin.select name="period" label="Periode" :options="$periods->mapWithKeys(fn ($p) => [$p => $p])" :value="request('period')" placeholder="Semua" />
        <x-admin.select name="category" label="Kategori" :options="$categories->mapWithKeys(fn ($c) => [$c => $c])" :value="request('category')" placeholder="Semua" />
    </x-admin.filters>

    <div class="flex items-center justify-between rounded-xl bg-zinc-900 px-5 py-3 text-sm text-zinc-300">
        <span>Total sesuai filter</span>
        <span class="text-base font-semibold text-white tabular-nums">{{ rupiah($total) }}</span>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th class="num">No</th><th>Tanggal</th><th>Periode</th><th>Nama barang</th><th>Jenis</th><th>Kategori</th><th>Bentuk</th>
                    <th>Diameter OD</th><th>Tebal</th><th>Grade</th><th class="num">Qty</th><th>Satuan</th><th class="num">Harga satuan</th><th class="num">Total</th><th>Keterangan / fungsi</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="num text-zinc-400">{{ $row->number }}</td>
                        <td class="whitespace-nowrap">{{ $row->date->translatedFormat('d M Y') }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $row->period }}</td>
                        <td class="min-w-44 font-medium">{{ $row->name }}</td>
                        <td class="text-xs">{{ $row->kind }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $row->category }}</td>
                        <td class="text-xs">{{ $row->form }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $row->diameter }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $row->thickness }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $row->grade }}</td>
                        <td class="num">{{ qty($row->qty) }}</td>
                        <td class="text-xs">{{ $row->unit }}</td>
                        <td class="num">{{ rupiah($row->unit_price) }}</td>
                        <td class="num font-semibold">{{ rupiah($row->total) }}</td>
                        <td class="min-w-64 text-xs text-zinc-500">{{ $row->description }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    {{ $rows->links() }}
@endsection
