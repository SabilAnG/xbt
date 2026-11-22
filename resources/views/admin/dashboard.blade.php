@php
    $icon = fn (string $name) => \App\Support\AdminNavigation::icon($name);
    $chartMax = max(1, $monthly->max('total_out') ?? 0, $monthly->max('revenue') ?? 0);
    // Garis bantu sumbu Y: 4 tingkat dibulatkan ke angka yang enak dibaca.
    $magnitude = 10 ** max(floor(log10($chartMax)) - 1, 0);
    $chartTop = ceil($chartMax / (4 * $magnitude)) * 4 * $magnitude;
    $short = function (float $n): string {
        return match (true) {
            abs($n) >= 1_000_000_000 => qty($n / 1_000_000_000, 1).' M',
            abs($n) >= 1_000_000 => qty($n / 1_000_000, 1).' jt',
            abs($n) >= 1_000 => qty($n / 1_000, 0).' rb',
            default => qty($n, 0),
        };
    };
    $categoryMax = max(1, $byCategory->max('total') ?? 0);
    $categoryTotal = $byCategory->sum('total');
    $alerts = [
        ['Produk stok habis', $headline['products_out'], route('admin.items.index', ['status' => 'HABIS']), $headline['products_out'] ? 'danger' : 'success'],
        ['Produk stok menipis', $headline['products_low'], route('admin.items.index', ['status' => 'MENIPIS']), $headline['products_low'] ? 'warning' : 'success'],
        ['Bahan habis / menipis', $headline['materials_alert'], route('admin.materials.index'), $headline['materials_alert'] ? 'danger' : 'success'],
        ['Pengeluaran perlu dirinci', $headline['needs_detail'], route('admin.expenses.index', ['data_status' => 'PERLU DIRINCI']), $headline['needs_detail'] ? 'danger' : 'success'],
        ['Piutang belum dibayar', rupiah($headline['receivable']), route('admin.sales.index', ['payment' => 'unpaid']), $headline['receivable'] > 0 ? 'danger' : 'success'],
        ['Gaji host belum ditransfer', rupiah($headline['host_unpaid']), route('admin.payrolls.index', 'host'), $headline['host_unpaid'] > 0 ? 'danger' : 'success'],
        ['Gaji tukang belum dibayar', rupiah($headline['worker_unpaid']), route('admin.payrolls.index', 'tukang'), $headline['worker_unpaid'] > 0 ? 'danger' : 'success'],
        ['Total stok knalpot jadi', qty($headline['total_stock']).' unit', route('admin.items.index'), 'default'],
    ];
    $alertTone = ['danger' => 'bg-rose-500', 'warning' => 'bg-amber-500', 'success' => 'bg-emerald-500', 'default' => 'bg-slate-300'];
@endphp
@extends('layouts.admin', ['title' => 'Dashboard', 'subtitle' => 'Ringkasan usaha knalpot — semua angka dihitung otomatis dari transaksi, sama dengan sheet Ringkasan'])

@section('actions')
    <a href="{{ route('admin.productions.create') }}" class="btn">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>Produksi
    </a>
    <a href="{{ route('admin.sales.create') }}" class="btn btn-primary">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>Penjualan
    </a>
@endsection

@section('content')
    {{-- KPI utama --}}
    <section class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.stat label="Total omzet" :value="rupiah($headline['revenue'])" :hint="'Uang masuk '.rupiah($headline['cash_in'])" :icon="$icon('cart')" tone="brand" :href="route('admin.sales.index')" />
        <x-admin.stat label="Laba kotor" :value="rupiah($headline['gross_profit'])" tone="success" :icon="$icon('coins')"
            :hint="$headline['revenue'] > 0 ? 'Margin '.qty($headline['gross_profit'] / $headline['revenue'] * 100, 1).'% dari omzet' : 'omzet − HPP'" />
        <x-admin.stat label="Total pengeluaran" :value="rupiah($headline['expenses'])" :icon="$icon('wallet')" :href="route('admin.expenses.index')" hint="Termasuk belanja bahan" />
        <x-admin.stat label="Nilai stok (HPP)" :value="rupiah($headline['stock_value'])" :icon="$icon('box')" :href="route('admin.items.index')" :hint="qty($headline['total_stock']).' unit knalpot jadi'" />
    </section>

    {{-- Perlu perhatian --}}
    <section class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Perlu perhatian</h2>
            <span class="hidden text-xs text-slate-400 sm:block">klik untuk membuka daftarnya</span>
        </div>
        <div class="grid grid-cols-1 gap-px bg-slate-100 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($alerts as [$label, $value, $href, $tone])
                <a href="{{ $href }}" class="flex items-center gap-3 bg-white px-5 py-3.5 transition hover:bg-slate-50">
                    <span class="size-2 shrink-0 rounded-full {{ $alertTone[$tone] }}"></span>
                    <span class="min-w-0 flex-1 truncate text-[13px] text-slate-600">{{ $label }}</span>
                    <span class="shrink-0 font-semibold text-slate-900 tabular-nums">{{ $value }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Grafik + kategori --}}
    <section class="grid gap-4 sm:gap-6 xl:grid-cols-3">
        <div class="card flex flex-col xl:col-span-2">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Omzet vs uang keluar</h2>
                    <p class="text-xs text-slate-500">12 bulan · pengeluaran + belanja bahan</p>
                </div>
                <div class="flex items-center gap-4 text-xs text-slate-500">
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-emerald-500"></span>Omzet</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-brand-500"></span>Keluar</span>
                </div>
            </div>
            <div class="flex flex-1 gap-3 p-5 pt-6">
                <div class="flex h-80 w-11 shrink-0 flex-col justify-between pb-6 text-right text-[11px] text-slate-400 tabular-nums">
                    @foreach ([4, 3, 2, 1, 0] as $step)
                        <span class="-translate-y-1/2">{{ $short($chartTop * $step / 4) }}</span>
                    @endforeach
                </div>
                <div class="scrollbar-thin min-w-0 flex-1 overflow-x-auto">
                    <div class="relative h-80 min-w-[520px]">
                        <div class="pointer-events-none absolute inset-x-0 top-0 bottom-6 flex flex-col justify-between">
                            @foreach ([4, 3, 2, 1, 0] as $step)<div @class(['border-t', 'border-dashed border-slate-200' => $step > 0, 'border-slate-300' => $step === 0])></div>@endforeach
                        </div>
                        <div class="relative flex h-full items-end gap-2">
                            @foreach ($monthly as $row)
                                <div class="group flex h-full flex-1 flex-col items-center justify-end">
                                    <div class="relative flex h-[calc(100%-1.5rem)] w-full items-end justify-center gap-[3px]">
                                        <div class="w-[38%] max-w-4 rounded-t-[3px] bg-emerald-500 transition group-hover:bg-emerald-600" style="height: {{ round($row['revenue'] / $chartTop * 100, 2) }}%"></div>
                                        <div class="w-[38%] max-w-4 rounded-t-[3px] bg-brand-500 transition group-hover:bg-brand-600" style="height: {{ round($row['total_out'] / $chartTop * 100, 2) }}%"></div>
                                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden w-48 -translate-x-1/2 rounded-lg bg-slate-900 px-3 py-2 text-[11px] text-white shadow-lg group-hover:block">
                                            <p class="mb-1 font-semibold">{{ $row['month']->translatedFormat('F Y') }}</p>
                                            <p class="flex justify-between gap-2"><span class="text-slate-300">Omzet</span>{{ rupiah($row['revenue']) }}</p>
                                            <p class="flex justify-between gap-2"><span class="text-slate-300">Keluar</span>{{ rupiah($row['total_out']) }}</p>
                                            <p class="flex justify-between gap-2"><span class="text-slate-300">Selisih kas</span>{{ rupiah($row['net_cash']) }}</p>
                                        </div>
                                    </div>
                                    <span class="mt-2 h-4 text-[11px] whitespace-nowrap text-slate-500">{{ $row['month']->translatedFormat('M y') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Pengeluaran per kategori</h2>
                <span class="text-sm font-semibold text-slate-900 tabular-nums">{{ rupiah($categoryTotal) }}</span>
            </div>
            <ul class="space-y-3 p-5">
                @foreach ($byCategory as $row)
                    <li>
                        <div class="mb-1.5 flex items-baseline justify-between gap-2 text-[13px]">
                            <span class="truncate text-slate-600">{{ $row['name'] }}</span>
                            <span class="shrink-0 font-medium text-slate-900 tabular-nums">{{ rupiah($row['total']) }}
                                <span class="ml-1 inline-block w-8 text-right text-[11px] font-normal text-slate-400">{{ $categoryTotal > 0 ? qty($row['total'] / $categoryTotal * 100, 0) : 0 }}%</span></span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-brand-600" style="width: {{ round($row['total'] / $categoryMax * 100, 2) }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Rekap per bulan --}}
    <section class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Rekap per bulan</h2>
            <span class="text-xs text-slate-400 lg:hidden">geser tabel ke samping</span>
        </div>
        <div class="scrollbar-thin overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Bulan</th><th class="num">Diproduksi</th><th class="num">Terjual</th><th class="num">Omzet</th><th class="num">Laba kotor</th>
                    <th class="num">Uang masuk</th><th class="num">Pengeluaran</th><th class="num">Belanja bahan</th><th class="num">Total keluar</th><th class="num">Selisih kas</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($monthly as $row)
                    @php $empty = ! $row['produced'] && ! $row['sold'] && ! $row['revenue'] && ! $row['total_out']; @endphp
                    <tr @class(['[&_td]:text-slate-400' => $empty])>
                        <td class="font-medium whitespace-nowrap text-slate-900">{{ $row['month']->translatedFormat('F Y') }}</td>
                        <td class="num">{{ qty($row['produced']) }}</td>
                        <td class="num">{{ qty($row['sold']) }}</td>
                        <td class="num">{{ rupiah($row['revenue']) }}</td>
                        <td class="num">{{ rupiah($row['gross_profit']) }}</td>
                        <td class="num">{{ rupiah($row['cash_in']) }}</td>
                        <td class="num">{{ rupiah($row['expenses']) }}</td>
                        <td class="num">{{ rupiah($row['purchases']) }}</td>
                        <td class="num">{{ rupiah($row['total_out']) }}</td>
                        <td @class(['num font-medium', 'text-rose-600' => $row['net_cash'] < 0, 'text-emerald-600' => $row['net_cash'] > 0])>{{ rupiah($row['net_cash']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="font-semibold text-slate-900">
                <tr>
                    <td>Total</td>
                    <td class="num">{{ qty($monthly->sum('produced')) }}</td>
                    <td class="num">{{ qty($monthly->sum('sold')) }}</td>
                    <td class="num">{{ rupiah($monthly->sum('revenue')) }}</td>
                    <td class="num">{{ rupiah($monthly->sum('gross_profit')) }}</td>
                    <td class="num">{{ rupiah($monthly->sum('cash_in')) }}</td>
                    <td class="num">{{ rupiah($monthly->sum('expenses')) }}</td>
                    <td class="num">{{ rupiah($monthly->sum('purchases')) }}</td>
                    <td class="num">{{ rupiah($monthly->sum('total_out')) }}</td>
                    <td @class(['num', 'text-rose-600' => $monthly->sum('net_cash') < 0, 'text-emerald-600' => $monthly->sum('net_cash') >= 0])>{{ rupiah($monthly->sum('net_cash')) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </section>

    {{-- Stok yang perlu ditindak --}}
    <section class="grid gap-4 sm:gap-6 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Bahan perlu dibeli</h2>
                <a href="{{ route('admin.purchases.create') }}" class="btn btn-sm">+ Pembelian</a>
            </div>
            <div class="scrollbar-thin max-h-[420px] overflow-auto">
                <table class="table">
                    <thead><tr><th>Bahan</th><th class="num">Sisa</th><th class="num">Minimum</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($alertMaterials as $material)
                        <tr>
                            <td><a href="{{ route('admin.materials.show', $material) }}" class="link">{{ $material->name }}</a></td>
                            <td class="num {{ $material->stock < 0 ? 'text-rose-600' : '' }}">{{ qty(round((float) $material->stock, 2)) }} <span class="text-slate-400">{{ $material->unit }}</span></td>
                            <td class="num text-slate-500">{{ qty($material->min_stock) }}</td>
                            <td><x-admin.status :value="$material->stockStatus()" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-slate-500">Semua bahan aman.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Knalpot stok menipis</h2>
                <a href="{{ route('admin.items.index', ['status' => 'MENIPIS']) }}" class="btn btn-ghost btn-sm">Lihat semua</a>
            </div>
            <div class="scrollbar-thin max-h-[420px] overflow-auto">
                <table class="table">
                    <thead><tr><th>Produk</th><th class="num">Sisa</th><th class="num">Minimum</th></tr></thead>
                    <tbody>
                    @forelse ($lowProducts as $item)
                        <tr>
                            <td><a href="{{ route('admin.items.show', $item) }}" class="link">{{ $item->name }}</a></td>
                            <td class="num font-medium text-amber-600">{{ qty($item->stock) }}</td>
                            <td class="num text-slate-500">{{ qty($item->min_stock) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-slate-500">Tidak ada produk yang menipis.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Aktivitas --}}
    <section class="grid gap-4 sm:gap-6 lg:grid-cols-3">
        @foreach ([
            ['Stok terbanyak', null, $topStock, 'stock'],
            ['Produksi terakhir', route('admin.productions.index'), $recentProductions, 'production'],
            ['Penjualan terakhir', route('admin.sales.index'), $recentSales, 'sale'],
        ] as [$heading, $more, $rows, $kind])
            <div class="card overflow-hidden">
                <div class="card-header">
                    <h2 class="card-title">{{ $heading }}</h2>
                    @if ($more)<a href="{{ $more }}" class="btn btn-ghost btn-sm">Semua</a>@endif
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <div class="min-w-0 flex-1">
                                @if ($kind === 'stock')
                                    <a href="{{ route('admin.items.show', $row) }}" class="block truncate text-[13px] text-slate-700 hover:text-brand-700">{{ $row->name }}</a>
                                @elseif ($kind === 'production')
                                    <p class="truncate text-[13px] text-slate-700">{{ $row->item?->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $row->produced_at->translatedFormat('d M Y') }} · {{ $row->production_number }}</p>
                                @else
                                    <p class="truncate text-[13px] text-slate-700">{{ $row->items->first()?->item?->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $row->sold_at->translatedFormat('d M Y') }} · {{ $row->channel ?? '—' }}</p>
                                @endif
                            </div>
                            @if ($kind === 'stock')
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 tabular-nums">{{ qty($row->stock) }}</span>
                            @elseif ($kind === 'production')
                                <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 tabular-nums">+{{ qty($row->output_qty) }}</span>
                            @else
                                <div class="space-y-1 text-right">
                                    <p class="text-[13px] font-semibold text-slate-900 tabular-nums">{{ rupiah($row->total) }}</p>
                                    <x-admin.status :value="$row->isPaidOff() ? 'LUNAS' : 'BELUM LUNAS'" />
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-slate-500">Belum ada data.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </section>
@endsection
