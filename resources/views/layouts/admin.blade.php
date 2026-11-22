<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite('resources/css/admin.css')
</head>
<body class="min-h-screen bg-zinc-100 font-sans text-zinc-800 antialiased">
@php
    $nav = [
        ['Ringkasan', [
            ['Dashboard', 'admin.dashboard', 'admin.dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'],
        ]],
        ['Stok', [
            ['Stok Produk', 'admin.items.index', 'admin.items.*', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ['Stok Bahan', 'admin.materials.index', 'admin.materials.*', 'M4 6h16M4 12h16M4 18h10'],
            ['Resep BOM', 'admin.formulas.index', 'admin.formulas.*', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ]],
        ['Transaksi', [
            ['Produksi', 'admin.productions.index', 'admin.productions.*', 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z'],
            ['Penjualan', 'admin.sales.index', 'admin.sales.*', 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z'],
            ['Pembelian Bahan', 'admin.purchases.index', 'admin.purchases.*', 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
            ['Penyesuaian Bahan', 'admin.adjustments.index', 'admin.adjustments.*', 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'],
            ['Pengeluaran', 'admin.expenses.index', 'admin.expenses.*', 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
        ]],
        ['Host Live & Tukang', [
            ['Jam Live', 'admin.live-sessions.index', 'admin.live-sessions.*', 'M15 10l4.6-2.3A1 1 0 0121 8.6v6.8a1 1 0 01-1.4.9L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
            ['Gaji Host', 'admin.payrolls.index', 'admin.payrolls.*:host', 'M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.1 0 2.1.4 2.6 1M12 8V7m0 1v8m0 0v1m0-1c-1.1 0-2.1-.4-2.6-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', ['kind' => 'host']],
            ['Absen Tukang', 'admin.attendance.index', 'admin.attendance.*', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['Gaji Tukang', 'admin.payrolls.index', 'admin.payrolls.*:tukang', 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z', ['kind' => 'tukang']],
            ['Data Host & Tukang', 'admin.hosts.index', 'admin.hosts.*|admin.workers.*', 'M17 20h5v-2a3 3 0 00-5.4-1.9M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.9M7 20H2v-2a3 3 0 015.4-1.9M7 20v-2c0-.7.1-1.3.4-1.9m0 0a5 5 0 019.2 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ]],
        ['Referensi', [
            ['Daftar Pilihan', 'admin.list-options.index', 'admin.list-options.*', 'M4 6h16M4 10h16M4 14h16M4 18h16'],
            ['Riwayat Pengadaan', 'admin.procurement.index', 'admin.procurement.*', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ]],
        ['Website', [
            ['Pengaturan Situs', 'admin.site-settings.edit', 'admin.site-settings.*', 'M10.3 4.3c.4-1.7 2.9-1.7 3.4 0a1.7 1.7 0 002.6 1.1c1.5-.9 3.3.8 2.4 2.4a1.7 1.7 0 001 2.5c1.8.5 1.8 2.9 0 3.4a1.7 1.7 0 00-1 2.6c.9 1.5-.9 3.3-2.4 2.4a1.7 1.7 0 00-2.6 1c-.5 1.8-2.9 1.8-3.4 0a1.7 1.7 0 00-2.5-1c-1.6.9-3.3-.9-2.4-2.4a1.7 1.7 0 00-1.1-2.6c-1.7-.5-1.7-2.9 0-3.4a1.7 1.7 0 001.1-2.5c-.9-1.6.8-3.3 2.4-2.4 1 .6 2.3.1 2.5-1.1zM15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ['Konten Halaman', 'admin.content-blocks.index', 'admin.content-blocks.*', 'M4 5h16M4 10h16M4 15h10M4 20h7'],
            ['Katalog Produk', 'admin.web-products.index', 'admin.web-products.*', 'M4 16l4.6-4.6a2 2 0 012.8 0L16 16m-2-2l1.6-1.6a2 2 0 012.8 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['Pesanan & Tracking', 'admin.orders.index', 'admin.orders.*', 'M9 17a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.6a1 1 0 01.7.3l3.4 3.4a1 1 0 01.3.7V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1'],
        ]],
    ];
@endphp

<div class="flex min-h-screen">
    <input type="checkbox" id="nav-toggle" class="peer hidden">
    <label for="nav-toggle" class="fixed inset-0 z-30 hidden bg-zinc-900/40 peer-checked:block lg:hidden"></label>

    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-zinc-900 text-zinc-300 transition-transform peer-checked:translate-x-0 lg:static lg:translate-x-0">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-5 py-5">
            <span class="grid size-9 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white">XBT</span>
            <span class="leading-tight">
                <span class="block text-sm font-semibold text-white">Knalpot Admin</span>
                <span class="block text-xs text-zinc-400">Produksi &amp; Penjualan</span>
            </span>
        </a>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-6">
            @foreach ($nav as [$group, $links])
                <div>
                    <p class="px-3 pb-2 text-[11px] font-semibold tracking-wider text-zinc-500 uppercase">{{ $group }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($links as $link)
                            @php
                                [$label, $route, $pattern, $icon] = $link;
                                $params = $link[4] ?? [];
                                // Pola "nama:kind" aktif hanya bila parameter kind cocok (Gaji Host vs Gaji Tukang).
                                $active = collect(explode('|', $pattern))->contains(function ($p) {
                                    [$name, $kind] = array_pad(explode(':', $p, 2), 2, null);

                                    return request()->routeIs($name) && ($kind === null || request()->route('kind') === $kind);
                                });
                            @endphp
                            <li>
                                <a href="{{ route($route, $params) }}" @class([
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition',
                                    'bg-zinc-800 font-medium text-white' => $active,
                                    'hover:bg-zinc-800/60 hover:text-white' => ! $active,
                                ])>
                                    <svg class="size-[18px] shrink-0 {{ $active ? 'text-brand-500' : 'text-zinc-500' }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                                    {{ $label }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-zinc-800 px-5 py-4 text-xs">
            <p class="truncate font-medium text-zinc-200">{{ auth()->user()->name }}</p>
            <p class="truncate text-zinc-500">{{ auth()->user()->email }}</p>
            <div class="mt-3 flex items-center gap-3">
                <a href="{{ route('home') }}" class="text-zinc-400 hover:text-white" target="_blank">Lihat website ↗</a>
                <form method="POST" action="{{ route('admin.logout') }}" class="ml-auto">
                    @csrf
                    <button class="text-zinc-400 hover:text-white">Keluar</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-zinc-200 bg-white/90 px-4 py-3 backdrop-blur sm:px-6">
            <label for="nav-toggle" class="btn btn-sm lg:hidden" aria-label="Menu">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </label>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-semibold text-zinc-900">{{ $title ?? 'Admin' }}</h1>
                @isset($subtitle)
                    <p class="truncate text-xs text-zinc-500">{{ $subtitle }}</p>
                @endisset
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @yield('actions')
            </div>
        </header>

        <main class="min-w-0 flex-1 space-y-6 p-4 sm:p-6">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
