@extends('layouts.admin', ['title' => 'Penjualan'])

@section('actions')
    <a href="{{ route('admin.sales.create') }}" class="btn btn-primary">+ Input penjualan</a>
@endsection

@section('content')
    <x-admin.filters>
        <x-admin.field name="q" label="Cari" :value="request('q')" placeholder="No. nota, pembeli, produk" />
        <div>
            <label class="label" for="month">Bulan</label>
            <input id="month" name="month" type="month" value="{{ request('month') }}" class="input">
        </div>
        <x-admin.select name="channel" label="Saluran" :options="collect($channels)->mapWithKeys(fn ($c) => [$c => $c])" :value="request('channel')" placeholder="Semua" />
        <x-admin.select name="payment" label="Pembayaran" :options="['unpaid' => 'Belum lunas', 'refund' => 'Uang belum kembali']" :value="request('payment')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Tanggal</th><th>No.</th><th>Produk</th><th class="num">Unit</th>
                    <th>Saluran</th><th>Pembeli</th><th class="num">Total</th><th class="num">Uang masuk</th><th class="num">Sisa piutang</th>
                    <th>Status bayar</th><th class="num">Laba kotor</th><th>Tracking</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($sales as $sale)
                    @php $units = (float) $sale->items->sum('qty'); @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $sale->sold_at->translatedFormat('d M Y') }}</td>
                        <td class="font-mono text-xs"><a href="{{ route('admin.sales.show', $sale) }}" class="link">{{ $sale->invoice_number }}</a></td>
                        <td class="min-w-56">
                            {{ $sale->itemSummary() }}
                            @if ($sale->notes)<p class="text-xs text-slate-500">{{ $sale->notes }}</p>@endif
                        </td>
                        <td class="num {{ $units < 0 ? 'text-rose-600' : '' }}">{{ qty($units) }}</td>
                        <td>{{ $sale->channel ?? '—' }}@if ($sale->payments->last()?->wallet)<p class="text-[11px] text-slate-400">→ {{ $sale->payments->last()->wallet->name }}</p>@endif</td>
                        <td>{{ $sale->customer_name ?? '—' }}</td>
                        <td class="num font-semibold">{{ rupiah($sale->total) }}</td>
                        <td class="num">{{ rupiah($sale->paid_amount) }}</td>
                        <td class="num {{ $sale->receivable() > 0 ? 'text-rose-600' : '' }}">{{ rupiah(max($sale->receivable(), 0)) }}</td>
                        <td><x-admin.status :value="$sale->paymentStatus()" /></td>
                        <td class="num text-emerald-700">{{ rupiah($sale->total - $sale->total_cost) }}</td>
                        <td class="whitespace-nowrap text-[12px]">
                            @if ($sale->order)<a href="{{ route('admin.orders.edit', $sale->order) }}" class="link">{{ \App\Models\Order::STATUSES[$sale->order->status] ?? $sale->order->status }}</a>@else<span class="text-muted">—</span>@endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.sales.show', $sale) }}" class="btn btn-sm">{{ $sale->paymentStatus() === 'LUNAS' ? 'Lihat' : ($sale->receivable() > 0 ? 'Bayar' : 'Kembalikan') }}</a>
                            <x-admin.delete-button :action="route('admin.sales.destroy', $sale)" confirm="Hapus penjualan ini? Stok knalpot dan uang masuknya dikembalikan." />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="py-8 text-center text-slate-500">Belum ada penjualan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $sales->links() }}
@endsection
