@extends('layouts.admin', ['title' => 'Penjualan', 'subtitle' => 'Stok knalpot berkurang otomatis. Retur = qty minus.'])

@section('actions')
    <a href="{{ route('admin.sales.create') }}" class="btn btn-primary">+ Input penjualan</a>
@endsection

@section('content')
    <x-admin.filters>
        <div>
            <label class="label" for="month">Bulan</label>
            <input id="month" name="month" type="month" value="{{ request('month') }}" class="input">
        </div>
        <x-admin.select name="channel" label="Saluran" :options="collect($channels)->mapWithKeys(fn ($c) => [$c => $c])" :value="request('channel')" placeholder="Semua" />
        <x-admin.select name="payment" label="Pembayaran" :options="['unpaid' => 'Belum lunas']" :value="request('payment')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Tanggal</th><th>No.</th><th>Produk</th><th class="num">Qty</th><th class="num">Harga deal</th>
                    <th>Saluran</th><th>Pembeli</th><th class="num">Total</th><th class="num">Uang masuk</th><th class="num">Sisa piutang</th>
                    <th>Status bayar</th><th class="num">HPP</th><th class="num">Laba kotor</th><th class="num">Harga daftar</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($sales as $sale)
                    @php $line = $sale->items->first(); @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $sale->sold_at->translatedFormat('d M Y') }}</td>
                        <td class="font-mono text-xs">{{ $sale->invoice_number }}</td>
                        <td class="min-w-48">
                            {{ $line?->item?->name }}
                            @if ($sale->notes)<p class="text-xs text-zinc-500">{{ $sale->notes }}</p>@endif
                        </td>
                        <td class="num {{ ($line?->qty ?? 0) < 0 ? 'text-red-600' : '' }}">{{ qty($line?->qty) }}</td>
                        <td class="num">{{ rupiah($line?->unit_price) }}</td>
                        <td>{{ $sale->channel ?? '—' }}</td>
                        <td>{{ $sale->customer_name ?? '—' }}</td>
                        <td class="num font-semibold">{{ rupiah($sale->total) }}</td>
                        <td class="num">{{ rupiah($sale->paid_amount) }}</td>
                        <td class="num {{ $sale->receivable() > 0 ? 'text-red-600' : '' }}">{{ rupiah($sale->receivable()) }}</td>
                        <td><x-admin.status :value="$sale->isPaidOff() ? 'LUNAS' : 'BELUM LUNAS'" /></td>
                        <td class="num">{{ rupiah($sale->total_cost) }}</td>
                        <td class="num text-emerald-700">{{ rupiah($sale->total - $sale->total_cost) }}</td>
                        <td class="num text-zinc-500">{{ $line?->item ? rupiah($line->item->listPriceFor($sale->channel)) : '' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.sales.edit', $sale) }}" class="btn btn-sm">Ubah</a>
                            <x-admin.delete-button :action="route('admin.sales.destroy', $sale)" confirm="Hapus penjualan ini? Stok knalpot akan dikembalikan." />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="15" class="py-8 text-center text-zinc-500">Belum ada penjualan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $sales->links() }}
@endsection
