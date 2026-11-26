@extends('layouts.admin', ['title' => 'Penerimaan '.$receipt->receipt_number, 'subtitle' => $receipt->received_at->translatedFormat('d M Y').' · '.($receipt->purchase->supplier ?? 'tanpa supplier').($receipt->received_by ? ' · diterima oleh '.$receipt->received_by : '')])

@section('actions')
    <a href="{{ route('admin.purchases.show', $receipt->purchase) }}" class="btn">Pembelian {{ $receipt->purchase->invoice_number }}</a>
@endsection

@section('content')
    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Barang diterima</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Bahan</th><th>Masuk ke</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Nilai</th></tr></thead>
                <tbody>
                @foreach ($receipt->items as $row)
                    @php $line = $row->purchaseItem; @endphp
                    <tr>
                        <td><a href="{{ route('admin.materials.show', $line->material) }}" class="link">{{ $line->material->label() }}</a></td>
                        <td class="text-xs">{{ $row->warehouse->name }}</td>
                        <td class="num whitespace-nowrap">{{ qty($row->qty) }} {{ $line->material->purchaseUnit() }}{{ $line->bar_length_cm ? ' × '.qty($line->bar_length_cm, 2).' cm' : '' }}</td>
                        <td class="num">{{ rupiah($line->unit_price) }}</td>
                        <td class="num font-semibold">{{ rupiah((float) $row->qty * (float) $line->unit_price) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($pieces->isNotEmpty())
            <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">Batang pipa tercatat: {{ $pieces->pluck('code')->implode(', ') }}</p>
        @endif
    </section>

    @if ($receipt->notes)
        <section class="card p-5 text-sm whitespace-pre-line text-slate-600">{{ $receipt->notes }}</section>
    @endif

    <div class="border-t border-slate-200 pt-4">
        <x-admin.delete-button :action="route('admin.receipts.destroy', $receipt)" label="Hapus penerimaan" confirm="Hapus penerimaan ini? Stok barangnya ditarik kembali dari gudang." />
    </div>
@endsection
