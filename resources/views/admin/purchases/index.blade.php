@extends('layouts.admin', ['title' => 'Pembelian Bahan', 'subtitle' => 'Belanja bahan yang ada di Stok Bahan — stok bahan bertambah'])

@section('actions')
    <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary">+ Input pembelian</a>
@endsection

@section('content')
    <x-admin.filters>
        <div>
            <label class="label" for="month">Bulan</label>
            <input id="month" name="month" type="month" value="{{ request('month') }}" class="input">
        </div>
        <x-admin.select name="material" label="Bahan" :options="$materials->pluck('name', 'id')" :value="request('material')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr><th>Tanggal</th><th>No.</th><th>Bahan</th><th class="num">Qty</th><th class="num">Harga satuan</th><th class="num">Total</th><th>Toko</th><th>Dibayar dari</th><th>Di pengeluaran?</th><th>Catatan</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($purchases as $purchase)
                    @php $line = $purchase->items->first(); @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $purchase->purchased_at->translatedFormat('d M Y') }}</td>
                        <td class="font-mono text-xs">{{ $purchase->invoice_number }}</td>
                        <td class="min-w-48">{{ $line?->material?->name }}</td>
                        <td class="num">{{ qty($line?->qty) }} {{ $line?->material?->unit }}</td>
                        <td class="num">{{ rupiah($line?->unit_cost) }}</td>
                        <td class="num font-semibold">{{ rupiah($purchase->total) }}</td>
                        <td>{{ $purchase->vendor?->name ?? '—' }}</td>
                        <td class="text-xs">{{ $purchase->paid_from ?? '—' }}</td>
                        <td>
                            @if ($purchase->recorded_in_expenses)
                                <span class="text-xs text-slate-500" title="Uangnya sudah dicatat di Pengeluaran, tidak dihitung dobel">Ya</span>
                            @else
                                <span class="text-xs font-medium text-brand-700">Belum</span>
                            @endif
                        </td>
                        <td class="max-w-xs truncate text-xs text-slate-500" title="{{ $purchase->notes }}">{{ $purchase->notes }}</td>
                        <td class="text-right"><x-admin.delete-button :action="route('admin.purchases.destroy', $purchase)" confirm="Hapus pembelian ini? Stok bahan akan dikurangi kembali." /></td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="py-8 text-center text-slate-500">Belum ada pembelian.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $purchases->links() }}
@endsection
