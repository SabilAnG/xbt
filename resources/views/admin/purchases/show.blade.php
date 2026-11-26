@extends('layouts.admin', ['title' => 'Pembelian '.$purchase->invoice_number, 'subtitle' => $purchase->purchased_at->translatedFormat('d M Y').' · '.($purchase->supplier ?? 'tanpa supplier').($purchase->supplier_location ? ' ('.$purchase->supplier_location.')' : '').($purchase->supplier_invoice ? ' · nota '.$purchase->supplier_invoice : '').($purchase->buyer ? ' · dibeli oleh '.$purchase->buyer : '')])

@section('actions')
    @if (! $purchase->isService() && $purchase->outstandingQty() > 0)
        <form method="POST" action="{{ route('admin.purchases.cancel-outstanding', $purchase) }}" onsubmit="return confirm('Batalkan sisa barang yang belum datang? Tagihan ikut disesuaikan.')">@csrf<button class="btn">Batalkan sisa</button></form>
        <a href="{{ route('admin.receipts.create', ['pembelian' => $purchase->id]) }}" class="btn btn-primary">Terima barang</a>
    @endif
    @if ($purchase->supplierRecord)
        <a href="{{ route('admin.suppliers.show', $purchase->supplierRecord) }}" class="btn">Supplier</a>
    @endif
@endsection

@section('content')
    <section class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-admin.stat label="Total" :value="rupiah($purchase->total)" />
        <x-admin.stat label="Dibayar" :value="rupiah($purchase->paid_amount)" />
        <x-admin.stat label="Sisa hutang" :value="rupiah($purchase->payable())" :tone="$purchase->payable() > 0 ? 'danger' : 'success'" />
        @if ($purchase->receiveStatus())
            <x-admin.stat label="Penerimaan barang" :value="$purchase->receiveStatus()" :tone="$purchase->outstandingQty() > 0 ? 'warning' : 'success'" />
        @endif
        @if ($purchase->plan)
            <x-admin.stat label="Untuk rencana" :value="$purchase->plan->plan_number" :href="route('admin.plans.show', $purchase->plan)" />
        @endif
    </section>

    @if ($purchase->isService())
        <section class="card p-5 text-sm text-slate-700">
            <p class="font-semibold text-violet-700">Tagihan jasa vendor</p>
            <p class="mt-1">{{ $purchase->notes }}</p>
            @if ($purchase->receipt)
                <p class="mt-2">Dari penerimaan produksi <a href="{{ route('admin.productions.show', $purchase->receipt->production_id) }}" class="link">{{ $purchase->receipt->production->production_number }}</a>.</p>
            @endif
        </section>
    @endif

    <section @class(['card overflow-hidden', 'hidden' => $purchase->isService()])>
        <div class="card-header"><h2 class="card-title">Barang dipesan</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Bahan</th><th>Gudang tujuan</th><th class="num">Dipesan</th><th class="num">Diterima</th><th class="num">Belum datang</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
                <tbody>
                @foreach ($purchase->items as $line)
                    @php $unit = $line->material->purchaseUnit(); @endphp
                    <tr>
                        <td><a href="{{ route('admin.materials.show', $line->material) }}" class="link">{{ $line->material->label() }}</a></td>
                        <td class="text-xs">{{ ($line->warehouse ?? $line->material->warehouse)->name }}</td>
                        <td class="num whitespace-nowrap">{{ qty($line->qty) }} {{ $unit }}{{ $line->bar_length_cm ? ' × '.qty($line->bar_length_cm, 2).' cm' : '' }}</td>
                        <td class="num whitespace-nowrap text-emerald-700">{{ qty($line->received_qty) }}</td>
                        <td class="num whitespace-nowrap {{ $line->outstandingQty() > 0 ? 'font-semibold text-amber-600' : 'text-slate-400' }}">
                            {{ $line->outstandingQty() > 0 ? qty($line->outstandingQty()) : '—' }}
                            @if ((float) $line->cancelled_qty > 0)<p class="text-[11px] font-normal text-slate-500">batal {{ qty($line->cancelled_qty) }}</p>@endif
                        </td>
                        <td class="num">{{ rupiah($line->unit_price) }}</td>
                        <td class="num font-semibold">{{ rupiah($line->subtotal) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @unless ($purchase->isService())
        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Penerimaan</h2></div>
            <table class="table">
                <tbody>
                @forelse ($purchase->receipts as $receipt)
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap"><a href="{{ route('admin.receipts.show', $receipt) }}" class="link">{{ $receipt->receipt_number }}</a></td>
                        <td class="whitespace-nowrap">{{ $receipt->received_at->translatedFormat('d M Y') }}{{ $receipt->received_by ? ' · '.$receipt->received_by : '' }}</td>
                        <td class="text-xs text-slate-600">{{ $receipt->items->map(fn ($row) => $row->purchaseItem->material->name.' × '.qty($row->qty).' → '.$row->warehouse->name)->implode(' · ') }}</td>
                    </tr>
                @empty
                    <tr><td class="py-6 text-center text-slate-500">Barang belum diterima.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endunless

    <section class="grid gap-4 sm:gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Pembayaran</h2></div>
            <table class="table">
                <tbody>
                @forelse ($purchase->payments as $payment)
                    <tr>
                        <td class="whitespace-nowrap">{{ $payment->paid_at->translatedFormat('d M Y') }}</td>
                        <td class="text-sm">{{ $payment->wallet?->name ?? '—' }}{{ $payment->notes ? ' · '.$payment->notes : '' }}</td>
                        <td class="num font-semibold">{{ rupiah($payment->amount) }}</td>
                        <td class="text-right"><x-admin.delete-button :action="route('admin.purchases.payments.destroy', $payment)" label="✕" confirm="Hapus pembayaran ini?" /></td>
                    </tr>
                @empty
                    <tr><td class="py-6 text-center text-slate-500">Belum ada pembayaran.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($purchase->payable() > 0)
            <form method="POST" action="{{ route('admin.purchases.payments.store', $purchase) }}" class="card space-y-4 p-6">
                @csrf
                <h2 class="card-title">Bayar hutang</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field name="paid_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                    <x-admin.field name="amount" label="Nominal (Rp)" type="number" step="any" min="0" :value="$purchase->payable()" required />
                    <x-admin.wallet-select class="sm:col-span-2" placeholder="— pilih dompet —" />
                    <x-admin.field name="notes" label="Catatan" class="sm:col-span-2" />
                </div>
                <div class="flex justify-end"><button class="btn btn-primary">Simpan pembayaran</button></div>
            </form>
        @endif
    </section>

    @if ($purchase->notes && ! $purchase->isService())
        <section class="card p-5 text-sm whitespace-pre-line text-slate-600">{{ $purchase->notes }}</section>
    @endif

    <div @class(['border-t border-slate-200 pt-4', 'hidden' => $purchase->isService()])>
        <x-admin.delete-button :action="route('admin.purchases.destroy', $purchase)" label="Hapus pembelian" confirm="Hapus pembelian ini? Penerimaan, stok barang, dan pembayarannya ikut dibatalkan." />
    </div>
@endsection
