@extends('layouts.admin', ['title' => 'Pindah Barang', 'subtitle' => 'Perpindahan stok antar gudang. Total stok dan nilai persediaan tidak berubah, hanya lokasinya.'])

@section('actions')
    <a href="{{ route('admin.transfers.create') }}" class="btn btn-primary">+ Pindah barang</a>
@endsection

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nomor, barang, atau yang memindah">
        </div>
        <x-admin.select name="gudang" label="Gudang" :options="$warehouses" :value="request('gudang')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Nomor</th><th>Tanggal</th><th>Dari → ke</th><th>Barang</th><th>Dipindah oleh</th><th class="num">Nilai</th></tr></thead>
                <tbody>
                @forelse ($transfers as $transfer)
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap"><a href="{{ route('admin.transfers.show', $transfer) }}" class="link">{{ $transfer->transfer_number }}</a></td>
                        <td class="whitespace-nowrap">{{ $transfer->transferred_at->translatedFormat('d M Y') }}</td>
                        <td class="text-sm whitespace-nowrap">{{ $transfer->fromWarehouse->name }} → {{ $transfer->toWarehouse->name }}</td>
                        <td class="min-w-56 text-xs text-slate-600">{{ $transfer->items->map(fn ($line) => $line->material->name.($line->piece ? ' '.$line->piece->code : '').' × '.qty($line->qty, 2).' '.$line->material->stockUnit())->implode(' · ') }}</td>
                        <td class="text-sm">{{ $transfer->moved_by ?? '—' }}</td>
                        <td class="num">{{ rupiah($transfer->items->sum('value')) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada pindah barang.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($transfers->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $transfers->links() }}</div>@endif
    </div>
@endsection
