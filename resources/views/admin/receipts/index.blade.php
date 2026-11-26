@extends('layouts.admin', ['title' => 'Penerimaan'])

@section('content')
    <section class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Menunggu diterima</h2>
            <span class="text-xs text-slate-500">{{ $awaiting->count() }} pembelian</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Pembelian</th><th>Tanggal</th><th>Supplier</th><th>Belum datang</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($awaiting as $purchase)
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap"><a href="{{ route('admin.purchases.show', $purchase) }}" class="link">{{ $purchase->invoice_number }}</a></td>
                        <td class="whitespace-nowrap">{{ $purchase->purchased_at->translatedFormat('d M Y') }}</td>
                        <td>{{ $purchase->supplier ?? '—' }}</td>
                        <td class="min-w-56 text-xs text-slate-600">{{ $purchase->items->filter(fn ($line) => $line->outstandingQty() > 0)->map(fn ($line) => $line->material->name.' × '.qty($line->outstandingQty()).' '.$line->material->purchaseUnit())->implode(' · ') }}</td>
                        <td><x-admin.status :value="$purchase->receiveStatus()" /></td>
                        <td class="text-right"><a href="{{ route('admin.receipts.create', ['pembelian' => $purchase->id]) }}" class="btn btn-sm btn-primary">Terima</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">Tidak ada barang yang ditunggu.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nomor penerimaan, pembelian, supplier, atau penerima">
        </div>
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Nomor</th><th>Tanggal</th><th>Pembelian</th><th>Supplier</th><th>Diterima oleh</th><th>Barang</th><th class="num">Nilai</th></tr></thead>
                <tbody>
                @forelse ($receipts as $receipt)
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap"><a href="{{ route('admin.receipts.show', $receipt) }}" class="link">{{ $receipt->receipt_number }}</a></td>
                        <td class="whitespace-nowrap">{{ $receipt->received_at->translatedFormat('d M Y') }}</td>
                        <td class="font-mono text-xs whitespace-nowrap"><a href="{{ route('admin.purchases.show', $receipt->purchase) }}" class="link">{{ $receipt->purchase->invoice_number }}</a></td>
                        <td>{{ $receipt->purchase->supplier ?? '—' }}</td>
                        <td class="text-sm">{{ $receipt->received_by ?? '—' }}</td>
                        <td class="min-w-56 text-xs text-slate-600">{{ $receipt->items->map(fn ($row) => $row->purchaseItem->material->name.' × '.qty($row->qty).' '.$row->purchaseItem->material->purchaseUnit())->implode(' · ') }}</td>
                        <td class="num font-semibold">{{ rupiah($receipt->value()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-slate-500">Belum ada penerimaan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($receipts->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $receipts->links() }}</div>@endif
    </div>
@endsection
