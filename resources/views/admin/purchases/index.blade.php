@extends('layouts.admin', ['title' => 'Pembelian Bahan', 'subtitle' => 'Pesanan ke supplier. Stok bertambah saat barang diterima (bisa bertahap lewat Penerimaan); yang belum dibayar tercatat sebagai hutang.'])

@section('actions')
    <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary">+ Pembelian</a>
@endsection

@section('content')
    <section class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-admin.stat label="Hutang pembelian" :value="rupiah($payable)" :tone="$payable > 0 ? 'danger' : 'success'" :href="route('admin.purchases.index', ['bayar' => 'hutang'])" />
        <x-admin.stat label="Menunggu diterima" :value="$awaiting.' pembelian'" :tone="$awaiting > 0 ? 'warning' : 'success'" :href="route('admin.receipts.index')" />
    </section>

    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nomor, toko, pembeli, atau nota toko">
        </div>
        <x-admin.select name="supplier" label="Supplier" :options="$suppliers" :value="request('supplier')" placeholder="Semua" />
        <x-admin.select name="terima" label="Penerimaan" :options="['belum' => 'Belum diterima semua']" :value="request('terima')" placeholder="Semua" />
        <x-admin.select name="bayar" label="Pembayaran" :options="['hutang' => 'Belum lunas']" :value="request('bayar')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Nomor</th><th>Tanggal</th><th>Supplier</th><th>Dibeli oleh</th><th>Barang</th><th class="num">Total</th><th class="num">Sisa hutang</th><th>Barang</th><th>Bayar</th></tr></thead>
                <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap"><a href="{{ route('admin.purchases.show', $purchase) }}" class="link">{{ $purchase->invoice_number }}</a></td>
                        <td class="whitespace-nowrap">{{ $purchase->purchased_at->translatedFormat('d M Y') }}</td>
                        <td>{{ $purchase->supplier ?? '—' }}</td>
                        <td class="text-sm">{{ $purchase->buyer ?? '—' }}</td>
                        <td class="min-w-56 text-xs text-slate-600">@if ($purchase->isService())<span class="font-medium text-violet-700">Jasa vendor</span> · {{ $purchase->notes }}@else{{ $purchase->items->map(fn ($line) => $line->material->name.' × '.qty($line->qty).' '.$line->material->purchaseUnit())->implode(' · ') }}@endif</td>
                        <td class="num font-semibold">{{ rupiah($purchase->total) }}</td>
                        <td class="num {{ $purchase->payable() > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ $purchase->payable() > 0 ? rupiah($purchase->payable()) : '—' }}</td>
                        <td>@if ($purchase->receiveStatus())<x-admin.status :value="$purchase->receiveStatus()" />@else<span class="text-xs text-slate-400">jasa</span>@endif</td>
                        <td><x-admin.status :value="$purchase->paymentStatus()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-10 text-center text-slate-500">Belum ada pembelian.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($purchases->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $purchases->links() }}</div>@endif
    </div>
@endsection
