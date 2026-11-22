@php
    use App\Support\AdminNavigation;

    $groups = AdminNavigation::groups();
    $current = AdminNavigation::current(request());
    $user = auth()->user();
    $initials = collect(explode(' ', (string) $user?->name))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') ?: 'A';
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
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap">
    @vite('resources/css/admin.css')
</head>
<body class="min-h-screen font-sans antialiased">
<input type="checkbox" id="nav-toggle" class="peer hidden">

{{-- ---------------------------------------------------------------- Sidebar --}}
<label for="nav-toggle" class="fixed inset-0 z-40 hidden bg-slate-900/40 backdrop-blur-[2px] peer-checked:block lg:hidden" aria-hidden="true"></label>
<aside class="fixed inset-y-0 left-0 z-50 flex w-[264px] -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 peer-checked:translate-x-0 lg:translate-x-0">
    <div class="flex h-16 shrink-0 items-center gap-3 px-5">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
            <span class="grid size-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-[13px] font-bold tracking-tight text-white shadow-sm ring-1 ring-brand-700/20">XBT</span>
            <span class="leading-tight">
                <span class="block text-sm font-semibold tracking-tight text-slate-900">Knalpot Admin</span>
                <span class="block text-xs text-slate-500">Produksi &amp; Penjualan</span>
            </span>
        </a>
        <label for="nav-toggle" class="btn btn-ghost btn-sm ml-auto px-1.5 lg:hidden" aria-label="Tutup menu">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
        </label>
    </div>

    <div class="px-4 pb-3">
        <div class="relative">
            <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15Z"/></svg>
            <input id="nav-search" type="search" placeholder="Cari menu…" autocomplete="off" class="input h-9 bg-slate-50 pl-9 shadow-none">
            <kbd class="pointer-events-none absolute top-1/2 right-2.5 hidden -translate-y-1/2 rounded border border-slate-200 bg-white px-1.5 text-[10px] font-medium text-slate-400 sm:block">/</kbd>
        </div>
    </div>

    <nav class="scrollbar-thin flex-1 space-y-1 overflow-y-auto px-3 pb-4" id="sidebar-nav">
        @foreach ($groups as $group)
            @php $groupActive = collect($group['items'])->contains(fn ($item) => AdminNavigation::isActive($item, request())); @endphp
            <div class="nav-group" data-group="{{ $group['label'] }}" @if ($groupActive) data-active @endif>
                <button type="button" class="nav-group-toggle flex w-full items-center justify-between rounded-md px-2.5 pt-3 pb-1.5 text-[11px] font-semibold tracking-wider text-slate-400 uppercase hover:text-slate-600">
                    {{ $group['label'] }}
                    <svg class="size-3.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>
                <ul class="nav-group-items space-y-0.5">
                    @foreach ($group['items'] as $item)
                        <li data-label="{{ strtolower($item['label'].' '.$group['label']) }}">
                            <a href="{{ route($item['route'], $item['params']) }}" class="nav-item" @if (AdminNavigation::isActive($item, request())) aria-current="page" @endif>
                                <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $item['icon'] }}"/></svg>
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        <p id="nav-empty" class="hidden px-3 py-6 text-center text-xs text-slate-400">Menu tidak ditemukan.</p>
    </nav>

    <div class="border-t border-slate-200 p-3">
        <a href="{{ route('home') }}" target="_blank" class="nav-item">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 4h6v6m0-6L10 14m-1-9H5a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1v-4"/></svg>
            Lihat website
        </a>
    </div>
</aside>

{{-- ------------------------------------------------------------------ Konten --}}
<div class="flex min-h-screen min-w-0 flex-col lg:pl-[264px]">
    <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200/80 bg-white/80 px-4 backdrop-blur-md sm:px-6 lg:px-8">
        <label for="nav-toggle" class="btn btn-ghost -ml-1.5 px-2 lg:hidden" aria-label="Buka menu">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </label>

        <nav class="flex min-w-0 items-center gap-1.5 text-[13px]" aria-label="Breadcrumb">
            <a href="{{ route('admin.dashboard') }}" class="hidden text-slate-400 hover:text-slate-600 sm:block">Admin</a>
            @if ($current && $current['group'] !== 'Ringkasan')
                <svg class="hidden size-3.5 shrink-0 text-slate-300 sm:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                <span class="hidden truncate text-slate-400 sm:block">{{ $current['group'] }}</span>
            @endif
            @if ($current)
                <svg class="hidden size-3.5 shrink-0 text-slate-300 sm:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 6 6 6-6 6"/></svg>
                <a href="{{ route($current['item']['route'], $current['item']['params']) }}" class="truncate font-medium text-slate-700 hover:text-slate-900">{{ $current['item']['label'] }}</a>
            @endif
        </nav>

        <div class="ml-auto flex items-center gap-2">
            <span class="hidden text-xs text-slate-400 md:block">{{ now()->translatedFormat('l, d F Y') }}</span>
            <details class="group relative">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full py-1 pr-2 pl-1 hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
                    <span class="grid size-8 place-items-center rounded-full bg-slate-900 text-xs font-semibold text-white">{{ strtoupper($initials) }}</span>
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block text-[13px] font-medium text-slate-800">{{ $user?->name }}</span>
                    </span>
                    <svg class="size-4 text-slate-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="absolute right-0 mt-2 w-60 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-(--shadow-pop)">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <p class="truncate text-sm font-medium text-slate-900">{{ $user?->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $user?->email }}</p>
                    </div>
                    <div class="p-1.5">
                        <a href="{{ route('home') }}" target="_blank" class="nav-item">Lihat website</a>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button class="nav-item w-full text-rose-600 hover:bg-rose-50 hover:text-rose-700">Keluar</button>
                        </form>
                    </div>
                </div>
            </details>
        </div>
    </header>

    <main class="mx-auto w-full max-w-[1480px] min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        {{-- Judul halaman + aksi --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-[22px]">{{ $title ?? 'Admin' }}</h1>
                @isset($subtitle)
                    <p class="mt-1 max-w-3xl text-[13px] text-slate-500">{{ $subtitle }}</p>
                @endisset
            </div>
            @hasSection('actions')
                <div class="flex shrink-0 flex-wrap items-center gap-2">@yield('actions')</div>
            @endif
        </div>

        @if (session('status'))
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[13px] text-emerald-800" role="status">
                <svg class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5L20 7"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-[13px] text-rose-700" role="alert">
                <svg class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 8v5m0 3h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div class="space-y-6">
            @yield('content')
        </div>
    </main>

    <footer class="mx-auto w-full max-w-[1480px] px-4 pb-6 text-xs text-slate-400 sm:px-6 lg:px-8">
        {{ config('app.name') }} · Admin panel
    </footer>
</div>

<script>
    (() => {
        // Grup menu bisa dilipat; pilihan diingat di browser (opsional, aman bila storage diblokir).
        const KEY = 'admin.nav.collapsed';
        let collapsed = [];
        try { collapsed = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) {}
        const save = () => { try { localStorage.setItem(KEY, JSON.stringify(collapsed)); } catch (e) {} };

        document.querySelectorAll('.nav-group').forEach(group => {
            const name = group.dataset.group;
            const items = group.querySelector('.nav-group-items');
            const icon = group.querySelector('.nav-group-toggle svg');
            const apply = (closed) => { items.hidden = closed; icon.style.transform = closed ? 'rotate(-90deg)' : ''; };
            apply(collapsed.includes(name) && !group.hasAttribute('data-active'));
            group.querySelector('.nav-group-toggle').addEventListener('click', () => {
                const closed = !items.hidden;
                apply(closed);
                collapsed = collapsed.filter(n => n !== name);
                if (closed) collapsed.push(name);
                save();
            });
        });

        // Cari menu: tekan "/" untuk fokus.
        const search = document.getElementById('nav-search');
        search.addEventListener('input', () => {
            const q = search.value.trim().toLowerCase();
            let shown = 0;
            document.querySelectorAll('.nav-group').forEach(group => {
                let any = false;
                group.querySelectorAll('li[data-label]').forEach(li => {
                    const match = !q || li.dataset.label.includes(q);
                    li.hidden = !match;
                    any = any || match;
                    if (match) shown++;
                });
                group.hidden = !any;
                if (q) group.querySelector('.nav-group-items').hidden = false;
            });
            document.getElementById('nav-empty').classList.toggle('hidden', shown > 0);
        });
        search.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                const first = document.querySelector('#sidebar-nav li[data-label]:not([hidden]) a');
                if (first) location.href = first.href;
            }
        });
        document.addEventListener('keydown', e => {
            if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                search.focus();
            }
        });

        // Tutup menu pengguna saat klik di luar.
        document.addEventListener('click', e => {
            document.querySelectorAll('details[open]').forEach(d => { if (!d.contains(e.target)) d.removeAttribute('open'); });
        });

        // Gulir item aktif ke tampilan.
        document.querySelector('.nav-item[aria-current="page"]')?.scrollIntoView({ block: 'nearest' });
    })();
</script>
</body>
</html>
