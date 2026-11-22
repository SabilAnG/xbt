@extends('layouts.admin', ['title' => 'Produksi', 'subtitle' => 'Setiap knalpot selesai dibuat: stok knalpot bertambah & bahan berkurang otomatis'])

@section('actions')
    <a href="{{ route('admin.productions.create') }}" class="btn btn-primary">+ Input produksi</a>
@endsection

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari produk</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama produk">
        </div>
        <div>
            <label class="label" for="month">Bulan</label>
            <input id="month" name="month" type="month" value="{{ request('month') }}" class="input">
        </div>
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>No.</th><th>Produk</th><th class="num">Qty jadi</th><th>Tukang</th><th>Resep</th><th>Merek</th><th>Catatan</th><th></th></tr></thead>
                <tbody>
                @forelse ($productions as $production)
                    <tr>
                        <td class="whitespace-nowrap">{{ $production->produced_at->translatedFormat('d M Y') }}</td>
                        <td><a href="{{ route('admin.productions.show', $production) }}" class="link font-mono text-xs">{{ $production->production_number }}</a></td>
                        <td>{{ $production->item?->name }}</td>
                        <td class="num font-semibold">{{ qty($production->output_qty) }}</td>
                        <td>{{ $production->worker ?? '—' }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $production->formula?->name }}</td>
                        <td class="text-xs">{{ $production->item?->brand }}</td>
                        <td class="max-w-xs truncate text-xs text-zinc-500" title="{{ $production->notes }}">{{ $production->notes }}</td>
                        <td class="text-right"><x-admin.delete-button :action="route('admin.productions.destroy', $production)" confirm="Hapus produksi ini? Stok knalpot dan bahan akan dikembalikan." /></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-zinc-500">Belum ada produksi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $productions->links() }}
@endsection
