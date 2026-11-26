@php
    $sourceLink = function ($row) {
        return match ($row->source_type) {
            \App\Models\Sale::class => ['Penjualan', route('admin.sales.edit', $row->source_id)],
            \App\Models\Expense::class => ['Pengeluaran', route('admin.expenses.edit', $row->source_id)],
            \App\Models\PurchasePayment::class => ['Pembelian bahan', route('admin.purchases.index')],
            \App\Models\WalletTransfer::class => ['Transfer', null],
            null => ['Manual', null],
            default => ['Lainnya', null],
        };
    };
@endphp
@extends('layouts.admin', ['title' => $wallet->name, 'subtitle' => (\App\Models\Wallet::TYPES[$wallet->type] ?? $wallet->type).($wallet->account_number ? ' · '.$wallet->account_number : '').($wallet->holder_name ? ' · a.n. '.$wallet->holder_name : '')])

@section('actions')
    <a href="{{ route('admin.wallets.index') }}" class="btn">Kembali</a>
    <a href="{{ route('admin.wallets.manual', ['wallet' => $wallet->id]) }}" class="btn">± Mutasi</a>
    <a href="{{ route('admin.wallets.edit', $wallet) }}" class="btn btn-primary">Ubah</a>
@endsection

@section('content')
    <section class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.stat label="Saldo sekarang" :value="rupiah($wallet->current_balance)" :tone="$wallet->current_balance < 0 ? 'danger' : 'brand'" />
        <x-admin.stat label="Saldo awal" :value="rupiah($wallet->opening_balance)" />
        <x-admin.stat label="Total masuk" :value="rupiah($totalIn)" tone="success" />
        <x-admin.stat label="Total keluar" :value="rupiah($totalOut)" tone="danger" />
    </section>

    <x-admin.filters>
        <div>
            <label class="label" for="month">Bulan</label>
            <input id="month" name="month" type="month" value="{{ request('month') }}" class="input">
        </div>
        <x-admin.select name="direction" label="Arah" :options="['in' => 'Masuk', 'out' => 'Keluar']" :value="request('direction')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Sumber</th><th>Keterangan</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Saldo</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    @php [$label, $href] = $sourceLink($row); @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $row->occurred_at->translatedFormat('d M Y') }}</td>
                        <td class="whitespace-nowrap">
                            @if ($href)<a href="{{ $href }}" class="link">{{ $label }}</a>@else<span class="text-slate-500">{{ $label }}</span>@endif
                        </td>
                        <td class="min-w-56">{{ $row->description }}</td>
                        <td class="num text-emerald-600">{{ $row->direction === 'in' ? '+'.rupiah($row->amount) : '' }}</td>
                        <td class="num text-rose-600">{{ $row->direction === 'out' ? '−'.rupiah($row->amount) : '' }}</td>
                        <td class="num font-semibold">{{ rupiah($row->balance_after) }}</td>
                        <td class="text-right">
                            @if ($row->source_type === null)
                                <x-admin.delete-button :action="route('admin.wallets.transactions.destroy', $row)" confirm="Hapus mutasi manual ini?" />
                            @elseif ($row->source_type === \App\Models\WalletTransfer::class)
                                <x-admin.delete-button :action="route('admin.wallets.transfers.destroy', $row->source_id)" confirm="Hapus transfer ini? Kedua dompet akan dikoreksi." />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-slate-500">Belum ada mutasi di dompet ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $rows->links() }}

    @if ($wallet->transactions()->doesntExist())
        <div class="flex justify-end"><x-admin.delete-button :action="route('admin.wallets.destroy', $wallet)" label="Hapus dompet" /></div>
    @endif
@endsection
