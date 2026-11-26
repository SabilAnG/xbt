@extends('layouts.admin', ['title' => 'Laporan Keuangan', 'subtitle' => 'Laba rugi '.$from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y')])

@section('content')
    <x-admin.filters>
        <x-admin.field name="dari" label="Dari" type="date" :value="$from->toDateString()" />
        <x-admin.field name="sampai" label="Sampai" type="date" :value="$to->toDateString()" />
    </x-admin.filters>

    <section class="stat-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Omzet penjualan" :value="rupiah($profit['revenue'])" />
        <x-admin.stat label="Laba kotor" :value="rupiah($profit['gross'])" :tone="$profit['gross'] >= 0 ? 'success' : 'danger'" />
        <x-admin.stat label="Biaya operasional" :value="rupiah($profit['operating'])" tone="warning" />
        <x-admin.stat label="Laba bersih" :value="rupiah($profit['net'])" :tone="$profit['net'] >= 0 ? 'success' : 'danger'" />
    </section>

    <section class="grid gap-4 sm:gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Laba rugi</h2></div>
            <table class="table">
                <tbody>
                <tr><td>Penjualan</td><td class="num">{{ rupiah($profit['revenue']) }}</td></tr>
                <tr><td class="pl-8 text-slate-500">HPP knalpot terjual</td><td class="num text-rose-600">−{{ rupiah($profit['cogs']) }}</td></tr>
                <tr class="font-semibold"><td>Laba kotor</td><td class="num">{{ rupiah($profit['gross']) }}</td></tr>
                @foreach ($profit['by_category'] as $category => $amount)
                    <tr><td class="pl-8 text-slate-500">{{ $category }}</td><td class="num text-rose-600">−{{ rupiah($amount) }}</td></tr>
                @endforeach
                @if ($profit['absorbed_labour'] > 0)
                    <tr><td class="pl-8 text-slate-500">Upah sudah dibebankan ke HPP produksi <span class="text-[11px]">(ongkos formula)</span></td><td class="num text-emerald-600">+{{ rupiah($profit['absorbed_labour']) }}</td></tr>
                @endif
                @if (abs($profit['stock_result']) > 0.004)
                    <tr><td class="pl-8 text-slate-500">Selisih stok <span class="text-[11px]">(opname, rusak, hilang, afkir)</span></td><td class="num {{ $profit['stock_result'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $profit['stock_result'] < 0 ? '−' : '+' }}{{ rupiah(abs($profit['stock_result'])) }}</td></tr>
                @endif
                <tr class="font-semibold"><td>Laba bersih</td><td class="num {{ $profit['net'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ rupiah($profit['net']) }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-4 sm:space-y-6">
            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Arus kas periode</h2></div>
                <table class="table">
                    <tbody>
                    <tr><td>Uang masuk dari penjualan</td><td class="num text-emerald-600">+{{ rupiah($cash['sales_in']) }}</td></tr>
                    <tr><td>Bayar pembelian bahan</td><td class="num text-rose-600">−{{ rupiah($cash['purchase_out']) }}</td></tr>
                    <tr><td>Pengeluaran operasional</td><td class="num text-rose-600">−{{ rupiah($cash['expense_out']) }}</td></tr>
                    @php $netCash = $cash['sales_in'] - $cash['purchase_out'] - $cash['expense_out']; @endphp
                    <tr class="font-semibold"><td>Kas bersih</td><td class="num {{ $netCash >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ rupiah($netCash) }}</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Produksi & pembelian periode</h2></div>
                <table class="table">
                    <tbody>
                    <tr><td>Produksi dibukukan</td><td class="num">{{ $production['count'] }} nota · {{ qty($production['units']) }} knalpot</td></tr>
                    <tr><td>Biaya produksi</td><td class="num">{{ rupiah($production['cost']) }}</td></tr>
                    <tr><td class="pl-8 text-slate-500">termasuk sisa pipa afkir</td><td class="num text-slate-500">{{ rupiah($production['waste']) }}</td></tr>
                    <tr><td>Pembelian bahan</td><td class="num">{{ rupiah($production['purchases']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:gap-6 lg:grid-cols-3">
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Kas & dompet</h2><span class="text-xs text-slate-500">saat ini</span></div>
            <table class="table">
                <tbody>
                @forelse ($position['wallets'] as $wallet)
                    <tr><td><a href="{{ route('admin.wallets.show', $wallet) }}" class="link">{{ $wallet->name }}</a></td><td class="num">{{ rupiah($wallet->current_balance) }}</td></tr>
                @empty
                    <tr><td class="py-6 text-center text-slate-500">Belum ada dompet.</td></tr>
                @endforelse
                <tr class="font-semibold"><td>Total kas</td><td class="num">{{ rupiah($position['wallets']->sum('current_balance')) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Persediaan</h2><span class="text-xs text-slate-500">nilai saat ini</span></div>
            <table class="table">
                <tbody>
                @foreach ($position['inventory'] as $name => $value)
                    <tr><td>{{ $name }}</td><td class="num">{{ rupiah($value) }}</td></tr>
                @endforeach
                <tr class="font-semibold"><td>Total persediaan</td><td class="num">{{ rupiah($position['inventory']->sum()) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Piutang & hutang</h2></div>
            <table class="table">
                <tbody>
                <tr><td><a href="{{ route('admin.sales.index') }}" class="link">Piutang penjualan</a></td><td class="num">{{ rupiah($position['receivable']) }}</td></tr>
                <tr><td><a href="{{ route('admin.purchases.index', ['bayar' => 'hutang']) }}" class="link">Hutang pembelian bahan</a></td><td class="num text-rose-600">{{ rupiah($position['payable']) }}</td></tr>
                @php $equity = $position['wallets']->sum('current_balance') + $position['inventory']->sum() + $position['receivable'] - $position['payable']; @endphp
                <tr class="font-semibold"><td>Kekayaan bersih usaha</td><td class="num">{{ rupiah($equity) }}</td></tr>
                </tbody>
            </table>
            <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">= kas + persediaan + piutang − hutang.</p>
        </div>
    </section>
@endsection
