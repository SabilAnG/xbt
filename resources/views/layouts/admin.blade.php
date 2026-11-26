@php
    use App\Support\AdminNavigation;

    $groups = AdminNavigation::groups();
    $current = AdminNavigation::current(request());
    $user = auth()->user();
    $initials = collect(explode(' ', (string) $user?->name))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') ?: 'A';
    $firstName = \Illuminate\Support\Str::of((string) $user?->name)->before(' ')->value() ?: 'Admin';
    $isDashboard = request()->routeIs('admin.dashboard');
    // Penanda titik merah: barang menunggu diterima & produksi masih draf.
    $awaitingGoods = \App\Models\MaterialPurchase::query()->awaitingGoods()->count();
    $draftProductions = \App\Models\Production::query()->where('status', \App\Models\Production::DRAFT)->count();
    $groupDots = ['Pembelian' => $awaitingGoods > 0, 'Produksi' => $draftProductions > 0];
    $lowMaterials = \App\Models\Material::query()->where('is_active', true)->get(['stock', 'min_stock'])
        ->filter(fn ($material) => $material->stockStatus() !== 'AMAN')->count();
    $notifications = collect([
        ['Pembelian menunggu barang', $awaitingGoods.' pembelian belum diterima semua', route('admin.receipts.index'), 'inbox', 'from-[#9fe3f5] to-[#2fa9d6]', $awaitingGoods],
        ['Produksi masih draf', $draftProductions.' nota produksi belum dibukukan', route('admin.productions.index', ['status' => 'draft']), 'factory', 'from-[#fde39a] to-[#eba50f]', $draftProductions],
        ['Bahan habis / menipis', $lowMaterials.' bahan di bawah stok minimum', route('admin.materials.index'), 'box', 'from-[#fdbac4] to-[#df3f58]', $lowMaterials],
    ])->filter(fn ($row) => $row[5] > 0)->values();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=poppins:400,500,600,700&display=swap">
    <script>
        // Sidebar lebar/sempit diingat di browser (aman bila storage diblokir).
        try { if (localStorage.getItem('admin.nav') === 'open') document.documentElement.dataset.nav = 'open'; } catch (e) {}
        // Tema terang/gelap: pilihan tersimpan, atau mengikuti pengaturan perangkat.
        (() => {
            let theme = null;
            try { theme = localStorage.getItem('admin.theme'); } catch (e) {}
            if (theme !== 'light' && theme !== 'dark') theme = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    @vite('resources/css/admin.css')
</head>
<body class="min-h-screen font-sans antialiased">
<svg width="0" height="0" class="absolute" aria-hidden="true">
    <defs>
        <linearGradient id="nav-a" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4f8bff"/><stop offset="1" stop-color="#6a45ee"/></linearGradient>
        <linearGradient id="nav-b" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#a8c3ff"/><stop offset="1" stop-color="#b9a3ff"/></linearGradient>
        <linearGradient id="ring-orange" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fbbf94"/><stop offset="1" stop-color="#e9643a"/></linearGradient>
        <linearGradient id="ring-cyan" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#d3f3fb"/><stop offset="1" stop-color="#3fbbe2"/></linearGradient>
        <linearGradient id="ring-yellow" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fde7a0"/><stop offset="1" stop-color="#eeaa12"/></linearGradient>
        <linearGradient id="ring-green" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#d2f0bd"/><stop offset="1" stop-color="#4fb83f"/></linearGradient>
        <linearGradient id="ring-violet" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e4d4fd"/><stop offset="1" stop-color="#9553e0"/></linearGradient>
        <linearGradient id="ring-rose" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fdc2ca"/><stop offset="1" stop-color="#e5465c"/></linearGradient>
    </defs>
</svg>
<input type="checkbox" id="nav-toggle" class="peer hidden">

{{-- ---------------------------------------------------------------- Sidebar --}}
<label for="nav-toggle" class="fixed inset-0 z-40 hidden bg-ink/30 backdrop-blur-[2px] peer-checked:block lg:hidden" aria-hidden="true"></label>
<aside class="rail fixed inset-y-0 left-0 z-50 flex w-[260px] -translate-x-full flex-col rounded-tr-[22px] bg-white shadow-[6px_0_30px_-20px_rgb(60_66_110/0.5)] transition-[width,transform] duration-200 peer-checked:translate-x-0 lg:w-(--rail) lg:translate-x-0">
    <div class="flex shrink-0 items-center gap-3 px-[19px] pt-6 pb-4 lg:[html:not([data-nav=open])_&]:justify-center lg:[html:not([data-nav=open])_&]:px-0">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
            <span class="relative grid size-[46px] shrink-0 place-items-center">
                <svg viewBox="0 0 46 46" class="absolute inset-0" aria-hidden="true">
                    <path d="M23 3 41 34H5Z" fill="#7b5cf0" opacity=".85"/>
                    <path d="M14 10 32 34H2Z" fill="#f0621f" opacity=".9"/>
                    <path d="M31 12 44 34H18Z" fill="#f7b21f" opacity=".9"/>
                </svg>
                <span class="relative mt-5 rounded-[5px] bg-[#e2361f] px-1 text-[11px] leading-[15px] font-extrabold tracking-tight text-white">XBT</span>
            </span>
            <span class="nav-label leading-tight">
                <span class="block text-sm font-semibold tracking-tight text-ink">Knalpot Admin</span>
                <span class="block text-xs text-muted">Produksi &amp; Penjualan</span>
            </span>
        </a>
        <label for="nav-toggle" class="tile ml-auto size-9 lg:hidden" aria-label="Tutup menu">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
        </label>
    </div>
    <button type="button" id="nav-expand" class="mx-auto mb-5 hidden text-accent-strong transition hover:translate-x-0.5 lg:block lg:[html[data-nav=open]_&]:mr-5 lg:[html[data-nav=open]_&]:rotate-180" aria-label="Lebarkan / sempitkan menu">
        <svg class="h-4 w-10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 40 16"><path d="M7 3.5C3.5 3.5 2 5.5 2 8s1.5 4.5 5 4.5h31m0 0-4-3.5m4 3.5-4 3.5"/></svg>
    </button>

    <nav class="scrollbar-thin flex-1 space-y-3 px-3 pb-6" id="sidebar-nav">
        @foreach ($groups as $group)
            @php
                $active = collect($group['items'])->contains(fn ($item) => AdminNavigation::isActive($item, request()));
                $first = $group['items'][0];
            @endphp
            <div class="nav-group relative" data-group="{{ $group['label'] }}">
                <a href="{{ route($first['route'], $first['params']) }}" class="group-tile" aria-current="{{ $active ? 'true' : 'false' }}" aria-label="{{ $group['label'] }}">
                    @include('layouts.partials.nav-glyph', ['group' => $group['label']])
                    @if ($groupDots[$group['label']] ?? false)
                        <span class="absolute top-2 left-[29px] size-2.5 rounded-full border-2 border-white bg-[#ef3b3b] lg:[html:not([data-nav=open])_&]:top-1.5 lg:[html:not([data-nav=open])_&]:left-[calc(50%+6px)]"></span>
                    @endif
                    <span class="nav-label truncate">{{ $group['label'] }}</span>
                </a>
                <div class="flyout">
                    <div class="flyout-panel">
                        <p class="flyout-title">{{ $group['label'] }}</p>
                        @foreach ($group['items'] as $item)
                            <a href="{{ route($item['route'], $item['params']) }}" class="flyout-link" data-key="{{ strtolower($item['label'].' '.$group['label']) }}" @if (AdminNavigation::isActive($item, request())) aria-current="page" @endif>
                                <svg fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $item['icon'] }}"/></svg>
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </nav>
</aside>

{{-- ------------------------------------------------------------------ Konten --}}
<div class="flex min-h-screen min-w-0 flex-col lg:pl-(--rail)">
    {{-- Nav atas: kartu kaca melayang, terpisah dari sidebar. --}}
    <header class="sticky top-0 z-30 w-full max-w-[1560px] px-3 pt-3 sm:px-6 sm:pt-4 lg:px-8 lg:pt-5 xl:px-10">
        <div class="flex h-[64px] items-center gap-2 rounded-[18px] border-[1.5px] border-white/90 bg-white/80 px-2.5 shadow-[0_16px_40px_-20px_rgb(60_66_110/0.35),0_2px_6px_-3px_rgb(60_66_110/0.08)] backdrop-blur-xl sm:gap-3 sm:px-3.5">
            <label for="nav-toggle" class="topbar-icon lg:hidden" aria-label="Buka menu">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </label>

            {{-- Form cari menu: "/" untuk fokus, Enter membuka hasil pertama. --}}
            <form role="search" class="relative min-w-0 flex-1 sm:max-w-[420px]" onsubmit="return false">
                <svg class="pointer-events-none absolute top-1/2 left-4 size-[18px] -translate-y-1/2 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15Z"/></svg>
                <input id="nav-search" type="search" placeholder="Cari menu, mis. pembelian, formula..." autocomplete="off" aria-label="Cari menu"
                       class="h-11 w-full rounded-[12px] border border-white bg-white pr-12 pl-11 text-[13px] text-ink shadow-[0_6px_16px_-10px_rgb(60_66_110/0.45)] placeholder:text-muted focus:border-brand-400 focus:ring-4 focus:ring-brand-500/12 focus:outline-none [&::-webkit-search-cancel-button]:hidden">
                <kbd class="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 rounded-md border border-line bg-surface px-1.5 py-0.5 text-[10px] font-semibold text-muted sm:block">/</kbd>
                <div id="nav-results" class="absolute right-0 left-0 z-40 mt-2 hidden max-h-80 min-w-64 overflow-y-auto rounded-2xl border border-white bg-white p-1.5 shadow-(--shadow-pop)"></div>
            </form>

            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                <span class="hidden items-center gap-2 px-2 text-[12.5px] font-medium text-ink-soft xl:inline-flex">
                    <svg class="size-[17px] text-accent" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" viewBox="0 0 24 24"><path d="M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                    {{ now()->translatedFormat('l, d M Y') }}
                </span>
                <span class="mx-1 hidden h-7 w-px bg-line xl:block"></span>

                {{-- Switch tema terang / gelap --}}
                <button type="button" class="theme-switch" id="theme-toggle" role="switch" aria-checked="false" aria-label="Mode gelap" title="Ganti tema terang / gelap">
                    <svg class="theme-icon left-[12px]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0-5a1 1 0 0 1 1 1v1.5a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 17.5a1 1 0 0 1 1 1V21a1 1 0 1 1-2 0v-.5a1 1 0 0 1 1-1ZM3 11h1.5a1 1 0 1 1 0 2H3a1 1 0 1 1 0-2Zm16.5 0H21a1 1 0 1 1 0 2h-1.5a1 1 0 1 1 0-2Z"/></svg>
                    <svg class="theme-icon right-[12px]" fill="currentColor" viewBox="0 0 24 24"><path d="M20.5 14.6A8.5 8.5 0 0 1 9.4 3.5a8.5 8.5 0 1 0 11.1 11.1Z"/></svg>
                    <span class="theme-knob">
                        <svg class="size-[17px] [html[data-theme=dark]_&]:hidden" fill="currentColor" viewBox="0 0 24 24"><path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0-5a1 1 0 0 1 1 1v1.5a1 1 0 1 1-2 0V3a1 1 0 0 1 1-1Zm0 17.5a1 1 0 0 1 1 1V21a1 1 0 1 1-2 0v-.5a1 1 0 0 1 1-1ZM3 11h1.5a1 1 0 1 1 0 2H3a1 1 0 1 1 0-2Zm16.5 0H21a1 1 0 1 1 0 2h-1.5a1 1 0 1 1 0-2Z"/></svg>
                        <svg class="hidden size-4 [html[data-theme=dark]_&]:block" fill="currentColor" viewBox="0 0 24 24"><path d="M20.5 14.6A8.5 8.5 0 0 1 9.4 3.5a8.5 8.5 0 1 0 11.1 11.1Z"/></svg>
                    </span>
                </button>

                {{-- Notifikasi --}}
                <details class="relative">
                    <summary class="topbar-icon list-none [&::-webkit-details-marker]:hidden" title="Notifikasi">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5a6.5 6.5 0 0 0-6.5 6.5v4.3L4 16v1.5h16V16l-1.5-2.7V9A6.5 6.5 0 0 0 12 2.5ZM9.5 19a2.5 2.5 0 0 0 5 0h-5Z"/></svg>
                        @if ($notifications->isNotEmpty())
                            <span class="topbar-badge">{{ min($notifications->sum(fn ($row) => $row[5]), 99) }}</span>
                        @endif
                    </summary>
                    <div class="absolute right-0 z-40 mt-3 w-[320px] overflow-hidden rounded-2xl border border-white bg-white shadow-(--shadow-pop)">
                        <div class="flex items-center justify-between border-b border-line px-4 py-3">
                            <p class="text-[14px] font-semibold text-ink">Notifikasi</p>
                            @if ($notifications->isNotEmpty())<span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-700">{{ $notifications->count() }} perlu ditindak</span>@endif
                        </div>
                        <div class="space-y-1 p-2">
                            @forelse ($notifications as [$label, $detail, $href, $icon, $gradient])
                                <a href="{{ $href }}" class="flex items-center gap-3 rounded-xl px-2.5 py-2.5 transition hover:bg-hover">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-[11px] bg-linear-to-br {{ $gradient }} text-white">
                                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ AdminNavigation::icon($icon) }}"/></svg>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-[13px] font-semibold text-ink">{{ $label }}</span>
                                        <span class="block truncate text-[11.5px] text-muted">{{ $detail }}</span>
                                    </span>
                                </a>
                            @empty
                                <div class="px-4 py-7 text-center">
                                    <span class="mx-auto mb-2 grid size-11 place-items-center rounded-full bg-emerald-50 text-emerald-600">
                                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m5 12 5 5L20 7"/></svg>
                                    </span>
                                    <p class="text-[13px] font-semibold text-ink">Semua beres</p>
                                    <p class="text-[11.5px] text-muted">Tidak ada yang perlu ditindak.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </details>
                <span class="mx-1 hidden h-7 w-px bg-line sm:block"></span>

                <details class="group relative">
                    <summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-[12px] py-1 pr-2 pl-1 transition hover:bg-white/80 [&::-webkit-details-marker]:hidden">
                        <span class="grid size-[38px] place-items-center rounded-[10px] bg-linear-to-br from-[#ffb36b] to-[#d9573a] text-[12.5px] font-semibold text-white shadow-(--shadow-soft)">{{ strtoupper($initials) }}</span>
                        <span class="hidden text-left leading-tight md:block">
                            <span class="block max-w-32 truncate text-[13px] font-semibold text-ink">{{ $user?->name }}</span>
                            <span class="block text-[11px] text-muted">Admin</span>
                        </span>
                        <svg class="hidden size-3 text-ink-soft transition group-open:rotate-180 md:block" viewBox="0 0 20 20" fill="currentColor"><path d="M5 7h10l-5 6z"/></svg>
                    </summary>
                    <div class="absolute right-0 z-40 mt-2 w-60 overflow-hidden rounded-2xl border border-white bg-white shadow-(--shadow-pop)">
                        <div class="border-b border-line px-4 py-3">
                            <p class="truncate text-sm font-medium text-ink">{{ $user?->name }}</p>
                            <p class="truncate text-xs text-muted">{{ $user?->email }}</p>
                        </div>
                        <div class="p-1.5">
                            <a href="{{ route('home') }}" target="_blank" class="flyout-link">Lihat website</a>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button class="flyout-link w-full text-rose-600 hover:bg-rose-50 hover:text-rose-700">Keluar</button>
                            </form>
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </header>

    <main class="w-full max-w-[1560px] min-w-0 flex-1 px-4 pt-6 pb-8 sm:px-6 lg:px-8 xl:px-10">
        {{-- Sapaan (dashboard) atau breadcrumb --}}
        @if ($isDashboard)
            <div class="mb-4">
                <p class="text-[24px] leading-tight font-semibold tracking-tight text-ink sm:text-[27px]">Halo {{ $firstName }}!</p>
                <p class="mt-1.5 max-w-md text-[12.5px] leading-snug font-medium text-ink-soft">Pantau perkembangan omzet, produksi, dan stok knalpot setiap bulannya.</p>
            </div>
        @else
            <nav class="mb-2 flex min-w-0 items-center gap-1.5 text-[12.5px] text-muted" aria-label="Breadcrumb">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-ink">Ringkasan</a>
                @if ($current && $current['group'] !== 'Ringkasan')
                    <span>/</span><span class="truncate">{{ $current['group'] }}</span>
                @endif
                @if ($current)
                    <span>/</span><a href="{{ route($current['item']['route'], $current['item']['params']) }}" class="truncate font-medium text-ink-soft hover:text-ink">{{ $current['item']['label'] }}</a>
                @endif
            </nav>
        @endif

        {{-- Judul halaman + aksi --}}
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-[22px] leading-tight font-semibold tracking-tight text-ink sm:text-[25px]">{{ $isDashboard ? 'Ringkasan' : ($title ?? 'Admin') }}</h1>
                @if (isset($subtitle) && ! $isDashboard)
                    <p class="mt-1.5 max-w-3xl text-[12.5px] leading-relaxed text-ink-soft">{{ $subtitle }}</p>
                @endif
            </div>
            @hasSection('actions')
                <div class="flex shrink-0 flex-wrap items-center gap-2.5">@yield('actions')</div>
            @endif
        </div>

        @include('layouts.partials.toasts')

        <div class="space-y-5">
            @yield('content')
        </div>
    </main>

    <footer class="w-full max-w-[1560px] px-4 pb-6 text-xs text-muted sm:px-6 lg:px-8 xl:px-10">
        {{ config('app.name') }} · Admin panel
    </footer>
</div>

<script>
    (() => {
        const root = document.documentElement;

        // Switch tema terang / gelap.
        const toggle = document.getElementById('theme-toggle');
        const paint = () => toggle?.setAttribute('aria-checked', root.dataset.theme === 'dark' ? 'true' : 'false');
        paint();
        toggle?.addEventListener('click', () => {
            root.classList.add('theme-switching');
            root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem('admin.theme', root.dataset.theme); } catch (e) {}
            paint();
            setTimeout(() => root.classList.remove('theme-switching'), 400);
        });

        // Lebarkan / sempitkan sidebar di desktop.
        document.getElementById('nav-expand')?.addEventListener('click', () => {
            const open = root.dataset.nav !== 'open';
            if (open) root.dataset.nav = 'open'; else delete root.dataset.nav;
            try { localStorage.setItem('admin.nav', open ? 'open' : 'closed'); } catch (e) {}
        });

        // Cari menu di header: tekan "/" untuk fokus, Enter membuka hasil pertama.
        const search = document.getElementById('nav-search');
        const results = document.getElementById('nav-results');
        const links = [...document.querySelectorAll('#sidebar-nav .flyout-link')].map(a => ({
            href: a.href, label: a.textContent.trim(), key: a.dataset.key,
            group: a.closest('.nav-group').dataset.group, icon: a.querySelector('svg').outerHTML,
        }));
        const render = () => {
            const q = search.value.trim().toLowerCase();
            if (!q) { results.classList.add('hidden'); return; }
            const found = links.filter(l => l.key.includes(q)).slice(0, 8);
            results.innerHTML = found.length
                ? found.map(l => `<a href="${l.href}" class="flyout-link">${l.icon}<span class="min-w-0 flex-1 truncate">${l.label}</span><span class="text-[11px] text-muted">${l.group}</span></a>`).join('')
                : '<p class="px-3 py-4 text-center text-xs text-muted">Menu tidak ditemukan.</p>';
            results.classList.remove('hidden');
        };
        if (search) {
            search.addEventListener('input', render);
            search.addEventListener('keydown', e => {
                if (e.key === 'Enter') { const first = results.querySelector('a'); if (first) location.href = first.href; }
                if (e.key === 'Escape') { search.value = ''; render(); search.blur(); }
            });
            document.addEventListener('keydown', e => {
                if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { e.preventDefault(); search.focus(); }
            });
        }

        // Tutup menu pengguna & hasil cari saat klik di luar.
        document.addEventListener('click', e => {
            document.querySelectorAll('details[open]').forEach(d => { if (!d.contains(e.target)) d.removeAttribute('open'); });
            if (search && !search.parentElement.contains(e.target)) results.classList.add('hidden');
        });
    })();
</script>
@stack('scripts')
@include('layouts.partials.custom-select')
</body>
</html>
