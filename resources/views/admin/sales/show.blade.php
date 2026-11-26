@extends('layouts.admin', ['title' => 'Penjualan '.$sale->invoice_number])

@section('actions')
    <a href="{{ route('admin.sales.index') }}" class="btn">Kembali</a>
    <a href="{{ route('admin.sales.edit', $sale) }}" class="btn">Ubah pembeli</a>
    @if ($sale->order)
        <a href="{{ route('admin.orders.edit', $sale->order) }}" class="btn">Tracking {{ $sale->order->order_number }}</a>
    @else
        <a href="{{ route('admin.orders.create', ['penjualan' => $sale->id]) }}" class="btn">+ Buat resi / tracking</a>
    @endif
    <button type="button" class="btn" onclick="window.print()">Cetak</button>
@endsection

@section('content')
    <section class="stat-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Total nota" :value="rupiah($sale->total)" :hint="$sale->sold_at->translatedFormat('d M Y').' · '.($sale->channel ?? 'tanpa saluran')" />
        <x-admin.stat label="Sudah dibayar" :value="rupiah($sale->paid_amount)" :hint="$sale->payments->count().' kali pembayaran'" />
        <x-admin.stat label="Sisa piutang" :value="rupiah(max($sale->receivable(), 0))" :tone="$sale->receivable() > 0 ? 'danger' : 'default'" trend="down" />
        <x-admin.stat label="Laba kotor" :value="rupiah($sale->total - $sale->total_cost)" :hint="'HPP '.rupiah($sale->total_cost)" />
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="min-w-0 space-y-5">
            <section class="card overflow-hidden">
                <div class="card-header">
                    <h2 class="card-title">Produk</h2>
                    <x-admin.status :value="$sale->isPaidOff() ? 'LUNAS' : 'BELUM LUNAS'" />
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Produk</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th><th class="num">HPP</th><th class="num">Laba</th></tr></thead>
                        <tbody>
                        @foreach ($sale->items as $line)
                            <tr>
                                <td>
                                    @if ($line->item)<a href="{{ route('admin.items.show', $line->item) }}" class="link">{{ $line->item->name }}</a>@else — @endif
                                    @if ($line->qty < 0)<span class="ml-1 text-[11px] font-semibold text-rose-600">retur</span>@endif
                                </td>
                                <td class="num {{ $line->qty < 0 ? 'text-rose-600' : '' }}">{{ qty($line->qty) }}</td>
                                <td class="num">{{ rupiah($line->unit_price) }}</td>
                                <td class="num font-medium">{{ rupiah($line->qty * $line->unit_price) }}</td>
                                <td class="num text-muted">{{ rupiah($line->qty * $line->unit_cost) }}</td>
                                <td class="num text-emerald-700">{{ rupiah($line->qty * ($line->unit_price - $line->unit_cost)) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr><td colspan="3" class="text-right text-muted">Subtotal</td><td class="num">{{ rupiah($sale->subtotal) }}</td><td colspan="2"></td></tr>
                            @if ((float) $sale->discount)
                                <tr><td colspan="3" class="text-right text-muted">Diskon</td><td class="num text-rose-600">−{{ rupiah($sale->discount) }}</td><td colspan="2"></td></tr>
                            @endif
                            <tr><td colspan="3" class="text-right font-semibold">Total</td><td class="num font-semibold">{{ rupiah($sale->total) }}</td><td colspan="2"></td></tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <section class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Riwayat pembayaran</h2></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Tanggal</th><th>Dompet</th><th>Catatan</th><th class="num">Nominal</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($sale->payments as $payment)
                            <tr>
                                <td class="whitespace-nowrap">{{ $payment->paid_at->translatedFormat('d M Y') }}</td>
                                <td>{{ $payment->wallet?->name ?? '—' }}</td>
                                <td class="text-muted">{{ $payment->notes }}</td>
                                <td class="num font-medium text-emerald-700">{{ rupiah($payment->amount) }}</td>
                                <td class="text-right"><x-admin.delete-button :action="route('admin.sales.payments.destroy', $payment)" confirm="Hapus pembayaran ini? Saldo dompet dikurangi lagi." /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-muted">Belum ada uang masuk untuk nota ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="card space-y-2 p-5 text-[13px]">
                <h2 class="card-title mb-2">Pembeli</h2>
                <p><span class="text-muted">Nama:</span> {{ $sale->customer_name ?? '—' }}</p>
                <p><span class="text-muted">Telepon:</span> {{ $sale->customer_phone ?? '—' }}</p>
                <p><span class="text-muted">Saluran:</span> {{ $sale->channel ?? '—' }}</p>
                @if ($sale->notes)<p class="whitespace-pre-line text-muted">{{ $sale->notes }}</p>@endif
                @if ($sale->order)
                    <p><span class="text-muted">Tracking:</span> <a href="{{ route('admin.orders.edit', $sale->order) }}" class="link">{{ $sale->order->order_number }}</a> · {{ \App\Models\Order::STATUSES[$sale->order->status] ?? $sale->order->status }}</p>
                @endif
            </section>

            @if ($sale->receivable() > 0)
                <form method="POST" action="{{ route('admin.sales.payments.store', $sale) }}" class="card space-y-4 p-5">
                    @csrf
                    <h2 class="card-title">Terima pembayaran</h2>
                    <x-admin.field name="paid_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                    <x-admin.field name="amount" label="Nominal (Rp)" type="number" step="any" min="0" :value="$sale->receivable()" required hint="Boleh dicicil." />
                    <x-admin.wallet-select label="Masuk ke dompet" placeholder="— pilih dompet —" />
                    <x-admin.field name="notes" label="Catatan" placeholder="mis. pelunasan, transfer BCA" />
                    <button class="btn btn-primary w-full">Simpan pembayaran</button>
                </form>
            @endif

            <div class="border-t border-line pt-4">
                <x-admin.delete-button :action="route('admin.sales.destroy', $sale)" label="Hapus nota" confirm="Hapus nota ini? Stok knalpot dan semua uang masuknya dikembalikan." />
            </div>
        </aside>
    </div>
@endsection
