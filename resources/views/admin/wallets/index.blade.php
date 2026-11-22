@extends('layouts.admin', ['title' => 'Dompet', 'subtitle' => 'Rekening bank, e-wallet, dan kas. Saldo bergerak otomatis dari penjualan, pengeluaran, pembelian bahan, dan transfer.'])

@php
    $typeIcon = [
        'bank' => 'M3 10h18M5 10v8m4-8v8m6-8v8m4-8v8M3 20h18M12 3 3 8h18l-9-5Z',
        'ewallet' => 'M7 3h10a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm4 15h2',
        'cash' => 'M3 7h18v10H3zm9 7.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
    ];
@endphp

@section('actions')
    <a href="{{ route('admin.wallets.manual') }}" class="btn">± Mutasi manual</a>
    <a href="{{ route('admin.wallets.transfer') }}" class="btn">⇄ Transfer</a>
    <a href="{{ route('admin.wallets.create') }}" class="btn btn-primary">+ Dompet</a>
@endsection

@section('content')
    <section class="card flex flex-wrap items-center justify-between gap-4 bg-slate-900 p-5 text-white">
        <div>
            <p class="text-xs text-slate-400">Total saldo semua dompet aktif</p>
            <p class="mt-1 text-2xl font-semibold tracking-tight tabular-nums">{{ rupiah($total) }}</p>
        </div>
        <p class="max-w-md text-xs leading-relaxed text-slate-400">Pilih dompet saat mencatat <strong class="text-slate-200">penjualan</strong> (uang masuk), <strong class="text-slate-200">pengeluaran</strong>, dan <strong class="text-slate-200">pembelian bahan</strong> (uang keluar).</p>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($wallets as $wallet)
            <a href="{{ route('admin.wallets.show', $wallet) }}" @class(['card group block p-5 transition hover:border-slate-300 hover:shadow-md', 'opacity-60' => ! $wallet->is_active])>
                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $typeIcon[$wallet->type] ?? $typeIcon['bank'] }}"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[15px] font-semibold text-slate-900">{{ $wallet->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ \App\Models\Wallet::TYPES[$wallet->type] ?? $wallet->type }}{{ $wallet->account_number ? ' · '.$wallet->account_number : '' }}</p>
                        @if ($wallet->holder_name)<p class="truncate text-xs text-slate-400">a.n. {{ $wallet->holder_name }}</p>@endif
                    </div>
                    @unless ($wallet->is_active)<x-admin.status value="NONAKTIF" />@endunless
                </div>
                <p @class(['mt-5 text-2xl font-semibold tracking-tight tabular-nums', 'text-rose-600' => $wallet->current_balance < 0, 'text-slate-900' => $wallet->current_balance >= 0])>{{ rupiah($wallet->current_balance) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $wallet->transactions_count }} mutasi · saldo awal {{ rupiah($wallet->opening_balance) }}</p>
            </a>
        @empty
            <div class="card p-10 text-center sm:col-span-2 xl:col-span-3">
                <p class="text-sm font-medium text-slate-900">Belum ada dompet</p>
                <p class="mt-1 text-sm text-slate-500">Daftarkan rekening bank, e-wallet (DANA, OVO, …), atau kas tunai.</p>
                <a href="{{ route('admin.wallets.create') }}" class="btn btn-primary mt-4">+ Tambah dompet</a>
            </div>
        @endforelse
    </section>

    @if ($recent->isNotEmpty())
        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Mutasi terakhir</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Tanggal</th><th>Dompet</th><th>Keterangan</th><th class="num">Masuk</th><th class="num">Keluar</th></tr></thead>
                    <tbody>
                    @foreach ($recent as $row)
                        <tr>
                            <td class="whitespace-nowrap">{{ $row->occurred_at->translatedFormat('d M Y') }}</td>
                            <td class="whitespace-nowrap"><a href="{{ route('admin.wallets.show', $row->wallet_id) }}" class="link">{{ $row->wallet?->name }}</a></td>
                            <td class="min-w-56">{{ $row->description }}</td>
                            <td class="num text-emerald-600">{{ $row->direction === 'in' ? '+'.rupiah($row->amount) : '' }}</td>
                            <td class="num text-rose-600">{{ $row->direction === 'out' ? '−'.rupiah($row->amount) : '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
