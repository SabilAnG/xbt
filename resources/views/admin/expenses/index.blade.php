@extends('layouts.admin', ['title' => 'Pengeluaran', 'subtitle' => 'Semua uang keluar selain belanja bahan yang dicatat stoknya'])

@section('actions')
    <a href="{{ route('admin.expenses.create') }}" class="btn btn-primary">+ Pengeluaran</a>
@endsection

@section('content')
    <x-admin.filters>
        <div class="min-w-48 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Keterangan atau catatan">
        </div>
        <div>
            <label class="label" for="month">Bulan</label>
            <input id="month" name="month" type="month" value="{{ request('month') }}" class="input">
        </div>
        <x-admin.select name="category" label="Kategori" :options="$categories->pluck('name', 'id')" :value="request('category')" placeholder="Semua" />
        <x-admin.select name="data_status" label="Status data" :options="collect($dataStatuses)->mapWithKeys(fn ($s) => [$s => $s])" :value="request('data_status')" placeholder="Semua" />
    </x-admin.filters>

    <div class="flex items-center justify-between rounded-xl bg-zinc-900 px-5 py-3 text-sm text-zinc-300">
        <span>Total sesuai filter</span>
        <span class="text-base font-semibold text-white tabular-nums">{{ rupiah($total) }}</span>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th class="num">Nominal</th><th>Ke rekening</th><th>Catatan</th><th>Status data</th><th></th></tr></thead>
                <tbody>
                @forelse ($expenses as $expense)
                    <tr @class(['bg-red-50/60' => $expense->data_status === 'PERLU DIRINCI'])>
                        <td class="whitespace-nowrap">{{ $expense->spent_at->translatedFormat('d M Y') }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $expense->category?->name }}</td>
                        <td class="min-w-48">{{ $expense->description }}</td>
                        <td class="num font-semibold">{{ rupiah($expense->amount) }}</td>
                        <td class="max-w-40 truncate text-xs" title="{{ $expense->paid_to }}">{{ $expense->paid_to ?? '—' }}</td>
                        <td class="max-w-xs text-xs text-zinc-500">{{ $expense->notes }}</td>
                        <td><x-admin.status :value="$expense->data_status ?? 'OK'" /></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.expenses.edit', $expense) }}" class="btn btn-sm">Ubah</a>
                            <x-admin.delete-button :action="route('admin.expenses.destroy', $expense)" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-zinc-500">Tidak ada pengeluaran.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $expenses->links() }}
@endsection
