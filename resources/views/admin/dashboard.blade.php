@extends('layouts.admin', ['title' => 'Dashboard', 'subtitle' => 'Ringkasan usaha knalpot — semua angka dihitung otomatis dari transaksi'])

@section('actions')
    <a href="{{ route('admin.productions.create') }}" class="btn hidden sm:inline-flex">+ Produksi</a>
    <a href="{{ route('admin.sales.create') }}" class="btn btn-primary">+ Penjualan</a>
@endsection

@section('content')
    {{-- Kartu ringkasan, padanan blok atas sheet "Ringkasan" --}}
    <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <x-admin.stat label="Total stok knalpot jadi" :value="qty($headline['total_stock']).' unit'" :href="route('admin.items.index')" />
        <x-admin.stat label="Nilai stok (HPP)" :value="rupiah($headline['stock_value'])" />
        <x-admin.stat label="Produk stok HABIS" :value="$headline['products_out']" tone="danger" :href="route('admin.items.index', ['status' => 'HABIS'])" />
        <x-admin.stat label="Produk stok MENIPIS" :value="$headline['products_low']" tone="warning" :href="route('admin.items.index', ['status' => 'MENIPIS'])" />
        <x-admin.stat label="Bahan HABIS / MENIPIS" :value="$headline['materials_alert']" :tone="$headline['materials_alert'] ? 'danger' : 'success'" :href="route('admin.materials.index')" />
        <x-admin.stat label="Pengeluaran perlu dirinci" :value="$headline['needs_detail']" :tone="$headline['needs_detail'] ? 'danger' : 'success'" :href="route('admin.expenses.index', ['data_status' => 'PERLU DIRINCI'])" />
    </section>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
        <x-admin.stat label="Total omzet" :value="rupiah($headline['revenue'])" />
        <x-admin.stat label="Laba kotor (omzet − HPP)" :value="rupiah($headline['gross_profit'])" tone="success" />
        <x-admin.stat label="Uang masuk" :value="rupiah($headline['cash_in'])" />
        <x-admin.stat label="Piutang belum dibayar" :value="rupiah($headline['receivable'])" :tone="$headline['receivable'] > 0 ? 'danger' : 'default'" :href="route('admin.sales.index', ['payment' => 'unpaid'])" />
        <x-admin.stat label="Total pengeluaran" :value="rupiah($headline['expenses'])" :href="route('admin.expenses.index')" />
        <x-admin.stat label="Gaji host BELUM ditransfer" :value="rupiah($headline['host_unpaid'])" :tone="$headline['host_unpaid'] > 0 ? 'danger' : 'default'" :href="route('admin.payrolls.index', 'host')" />
        <x-admin.stat label="Gaji tukang BELUM dibayar" :value="rupiah($headline['worker_unpaid'])" :tone="$headline['worker_unpaid'] > 0 ? 'danger' : 'default'" :href="route('admin.payrolls.index', 'tukang')" />
    </section>

    {{-- Grafik arus kas per bulan --}}
    @php
        $chartMax = max(1, $monthly->max('total_out') ?? 0, $monthly->max('revenue') ?? 0);
    @endphp
    <section class="grid gap-6 xl:grid-cols-3">
        <div class="card flex flex-col xl:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Omzet vs total keluar per bulan</h2>
                <div class="flex items-center gap-4 text-xs text-zinc-500">
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-emerald-500"></span>Omzet</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-brand-500"></span>Keluar</span>
                </div>
            </div>
            <div class="flex flex-1 overflow-x-auto p-5">
                <div class="flex h-auto min-h-56 w-full min-w-[480px] items-end gap-3">
                    @foreach ($monthly as $row)
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-1.5">
                            <div class="flex h-full w-full items-end justify-center gap-1">
                                <div class="w-1/2 max-w-5 rounded-t bg-emerald-500" style="height: {{ round($row['revenue'] / $chartMax * 100, 2) }}%" title="Omzet {{ rupiah($row['revenue']) }}"></div>
                                <div class="w-1/2 max-w-5 rounded-t bg-brand-500" style="height: {{ round($row['total_out'] / $chartMax * 100, 2) }}%" title="Keluar {{ rupiah($row['total_out']) }}"></div>
                            </div>
                            <span class="text-[11px] whitespace-nowrap text-zinc-500">{{ $row['month']->translatedFormat('M y') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Pengeluaran per kategori</h2></div>
            @php $categoryMax = max(1, $byCategory->max('total') ?? 0); $categoryTotal = $byCategory->sum('total'); @endphp
            <ul class="space-y-3 p-5">
                @forelse ($byCategory as $row)
                    <li>
                        <div class="mb-1 flex justify-between gap-2 text-sm">
                            <span class="truncate text-zinc-700">{{ $row['name'] }}</span>
                            <span class="tabular-nums text-zinc-900">{{ rupiah($row['total']) }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-zinc-100">
                            <div class="h-1.5 rounded-full bg-brand-500" style="width: {{ round($row['total'] / $categoryMax * 100, 2) }}%"></div>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-zinc-500">Belum ada pengeluaran.</li>
                @endforelse
                @if ($byCategory->isNotEmpty())
                    <li class="flex justify-between border-t border-zinc-100 pt-3 text-sm font-semibold">
                        <span>Total</span><span class="tabular-nums">{{ rupiah($categoryTotal) }}</span>
                    </li>
                @endif
            </ul>
        </div>
    </section>

    {{-- Tabel per bulan, padanan blok "PER BULAN" --}}
    <section class="card">
        <div class="card-header"><h2 class="card-title">Rekap per bulan</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Bulan</th>
                    <th class="num">Diproduksi</th>
                    <th class="num">Terjual</th>
                    <th class="num">Omzet</th>
                    <th class="num">Laba kotor</th>
                    <th class="num">Uang masuk</th>
                    <th class="num">Pengeluaran</th>
                    <th class="num">Belanja bahan</th>
                    <th class="num">Total keluar</th>
                    <th class="num">Selisih kas</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($monthly as $row)
                    <tr>
                        <td class="whitespace-nowrap font-medium">{{ $row['month']->translatedFormat('F Y') }}</td>
                        <td class="num">{{ qty($row['produced']) }}</td>
                        <td class="num">{{ qty($row['sold']) }}</td>
                        <td class="num">{{ rupiah($row['revenue']) }}</td>
                        <td class="num">{{ rupiah($row['gross_profit']) }}</td>
                        <td class="num">{{ rupiah($row['cash_in']) }}</td>
                        <td class="num">{{ rupiah($row['expenses']) }}</td>
                        <td class="num">{{ rupiah($row['purchases']) }}</td>
                        <td class="num">{{ rupiah($row['total_out']) }}</td>
                        <td class="num {{ $row['net_cash'] < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ rupiah($row['net_cash']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="bg-zinc-50 font-semibold">
                <tr>
                    <td class="px-4 py-2.5">TOTAL</td>
                    <td class="num px-4">{{ qty($monthly->sum('produced')) }}</td>
                    <td class="num px-4">{{ qty($monthly->sum('sold')) }}</td>
                    <td class="num px-4">{{ rupiah($monthly->sum('revenue')) }}</td>
                    <td class="num px-4">{{ rupiah($monthly->sum('gross_profit')) }}</td>
                    <td class="num px-4">{{ rupiah($monthly->sum('cash_in')) }}</td>
                    <td class="num px-4">{{ rupiah($monthly->sum('expenses')) }}</td>
                    <td class="num px-4">{{ rupiah($monthly->sum('purchases')) }}</td>
                    <td class="num px-4">{{ rupiah($monthly->sum('total_out')) }}</td>
                    <td class="num px-4 {{ $monthly->sum('net_cash') < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ rupiah($monthly->sum('net_cash')) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Bahan perlu dibeli (HABIS / MENIPIS)</h2>
                <a href="{{ route('admin.purchases.create') }}" class="btn btn-sm">+ Pembelian</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Bahan</th><th class="num">Sisa</th><th class="num">Minimum</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($alertMaterials as $material)
                        <tr>
                            <td><a href="{{ route('admin.materials.show', $material) }}" class="link">{{ $material->name }}</a></td>
                            <td class="num {{ $material->stock < 0 ? 'text-red-600' : '' }}">{{ qty(round((float) $material->stock, 2)) }} {{ $material->unit }}</td>
                            <td class="num">{{ qty($material->min_stock) }}</td>
                            <td><x-admin.status :value="$material->stockStatus()" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-zinc-500">Semua bahan aman.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Knalpot stok MENIPIS</h2>
                <a href="{{ route('admin.items.index', ['status' => 'MENIPIS']) }}" class="link text-xs">Lihat semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Produk</th><th class="num">Sisa</th><th class="num">Minimum</th></tr></thead>
                    <tbody>
                    @forelse ($lowProducts as $item)
                        <tr>
                            <td><a href="{{ route('admin.items.show', $item) }}" class="link">{{ $item->name }}</a></td>
                            <td class="num">{{ qty($item->stock) }}</td>
                            <td class="num">{{ qty($item->min_stock) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-zinc-500">Tidak ada produk yang menipis.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Stok terbanyak</h2></div>
            <ul class="divide-y divide-zinc-100">
                @forelse ($topStock as $item)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                        <a href="{{ route('admin.items.show', $item) }}" class="truncate text-zinc-700 hover:text-brand-700">{{ $item->name }}</a>
                        <span class="shrink-0 font-semibold tabular-nums">{{ qty($item->stock) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-3 text-sm text-zinc-500">Belum ada stok.</li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Produksi terakhir</h2>
                <a href="{{ route('admin.productions.index') }}" class="link text-xs">Semua</a>
            </div>
            <ul class="divide-y divide-zinc-100">
                @forelse ($recentProductions as $production)
                    <li class="px-5 py-2.5 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="truncate text-zinc-700">{{ $production->item?->name }}</span>
                            <span class="shrink-0 font-semibold tabular-nums">+{{ qty($production->output_qty) }}</span>
                        </div>
                        <p class="text-xs text-zinc-500">{{ $production->produced_at->translatedFormat('d M Y') }} · {{ $production->production_number }}</p>
                    </li>
                @empty
                    <li class="px-5 py-3 text-sm text-zinc-500">Belum ada produksi.</li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Penjualan terakhir</h2>
                <a href="{{ route('admin.sales.index') }}" class="link text-xs">Semua</a>
            </div>
            <ul class="divide-y divide-zinc-100">
                @forelse ($recentSales as $sale)
                    <li class="px-5 py-2.5 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="truncate text-zinc-700">{{ $sale->items->first()?->item?->name }}</span>
                            <span class="shrink-0 font-semibold tabular-nums">{{ rupiah($sale->total) }}</span>
                        </div>
                        <p class="flex items-center gap-2 text-xs text-zinc-500">
                            {{ $sale->sold_at->translatedFormat('d M Y') }} · {{ $sale->channel ?? '—' }}
                            <x-admin.status :value="$sale->isPaidOff() ? 'LUNAS' : 'BELUM LUNAS'" />
                        </p>
                    </li>
                @empty
                    <li class="px-5 py-3 text-sm text-zinc-500">Belum ada penjualan.</li>
                @endforelse
            </ul>
        </div>
    </section>
@endsection
