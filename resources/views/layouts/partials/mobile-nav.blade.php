{{--
    Navigasi gaya aplikasi untuk HP (di bawah lg): tab bawah, lembar Menu
    (semua fitur sesuai hak akses + akun), lembar Notifikasi, dan tombol aksi
    melayang (FAB) yang diambil dari tombol utama halaman.
--}}
@php
    $tabClass = fn (bool $on) => 'm-tab'.($on ? ' is-active' : '');
@endphp

{{-- ------------------------------------------------------------ tab bawah --}}
<nav class="m-tabbar lg:hidden" aria-label="Navigasi utama">
    <a href="{{ route('admin.dashboard') }}" class="{{ $tabClass($isDashboard) }}" @if ($isDashboard) aria-current="page" @endif>
        <span class="m-tab-icon">@include('layouts.partials.nav-glyph', ['group' => 'Ringkasan'])</span>
        <span class="m-tab-label">Ringkasan</span>
    </a>
    @foreach ($tabGroups as $group)
        @php $first = $group['items'][0]; $on = $currentGroup === $group['label']; @endphp
        <a href="{{ route($first['route'], $first['params']) }}" class="{{ $tabClass($on) }}" @if ($on) aria-current="page" @endif>
            <span class="m-tab-icon">
                @include('layouts.partials.nav-glyph', ['group' => $group['label']])
                @if ($groupDots[$group['label']] ?? false)<span class="m-tab-dot"></span>@endif
            </span>
            <span class="m-tab-label">{{ $group['label'] }}</span>
        </a>
    @endforeach
    <button type="button" class="{{ $tabClass($menuActive) }}" data-sheet-open="menu">
        <span class="m-tab-icon m-tab-icon-menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
            @if (collect($groupDots)->filter()->keys()->diff($tabGroups->pluck('label'))->isNotEmpty())<span class="m-tab-dot"></span>@endif
        </span>
        <span class="m-tab-label">Menu</span>
    </button>
</nav>

{{-- ------------------------------------------------------------ lembar Menu --}}
<div class="m-sheet lg:hidden" data-sheet="menu" hidden>
    <div class="m-sheet-backdrop" data-sheet-close></div>
    <section class="m-sheet-panel m-sheet-full" role="dialog" aria-modal="true" aria-label="Menu">
        <div class="m-sheet-handle" data-sheet-drag></div>
        <div class="m-sheet-head">
            <h2>Menu</h2>
            <button type="button" class="m-icon-btn" data-sheet-close aria-label="Tutup">
                <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="m-sheet-body">
            {{-- Akun --}}
            <div class="m-account">
                <span class="m-account-avatar">{{ strtoupper($initials) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[15px] font-semibold text-ink">{{ $user?->name }}</span>
                    <span class="block truncate text-[13px] text-muted">{{ $user?->roleLabel() }} · {{ $user?->email }}</span>
                </span>
            </div>
            @unless ($user?->hasTwoFactor())
                <a href="{{ route('admin.account.security') }}" class="m-alert">
                    <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="M12 9v4m0 3h.01"/></svg>
                    <span>Aktifkan verifikasi 2 langkah</span>
                    <svg class="ml-auto" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>
                </a>
            @endunless

            {{-- Cari --}}
            <label class="m-search">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15Z"/></svg>
                <input type="search" placeholder="Cari menu…" autocomplete="off" data-menu-search aria-label="Cari menu">
            </label>

            {{-- Semua grup sesuai hak akses --}}
            @foreach ($groups as $group)
                <div class="m-group" data-menu-group>
                    <p class="m-group-title">
                        <span class="m-group-glyph">@include('layouts.partials.nav-glyph', ['group' => $group['label']])</span>
                        {{ $group['label'] }}
                        @if ($groupDots[$group['label']] ?? false)<span class="m-tab-dot static"></span>@endif
                    </p>
                    <div class="m-list">
                        @foreach ($group['items'] as $item)
                            @php $on = \App\Support\AdminNavigation::isActive($item, request()); @endphp
                            <a href="{{ route($item['route'], $item['params']) }}" class="m-list-item{{ $on ? ' is-active' : '' }}" data-menu-item="{{ strtolower($item['label'].' '.$group['label']) }}">
                                <span class="m-list-icon"><svg fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $item['icon'] }}"/></svg></span>
                                <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                <svg class="m-chevron" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <p class="m-empty" data-menu-empty hidden>Menu tidak ditemukan.</p>

            {{-- Pengaturan & akun --}}
            <div class="m-group" data-menu-static>
                <p class="m-group-title">Akun</p>
                <div class="m-list">
                    <a href="{{ route('admin.account.security') }}" class="m-list-item">
                        <span class="m-list-icon"><svg fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/></svg></span>
                        <span class="flex-1">Keamanan akun</span>
                        <svg class="m-chevron" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>
                    </a>
                    <button type="button" class="m-list-item" data-theme-toggle role="switch" aria-checked="false">
                        <span class="m-list-icon"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M20.5 14.6A8.5 8.5 0 0 1 9.4 3.5a8.5 8.5 0 1 0 11.1 11.1Z"/></svg></span>
                        <span class="flex-1 text-left">Mode gelap</span>
                        <span class="m-switch" aria-hidden="true"><span></span></span>
                    </button>
                    <a href="{{ route('home') }}" target="_blank" class="m-list-item">
                        <span class="m-list-icon"><svg fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/><circle cx="12" cy="12" r="9"/></svg></span>
                        <span class="flex-1">Lihat website</span>
                        <svg class="m-chevron" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="m-list-item text-rose-600">
                            <span class="m-list-icon bg-rose-50! text-rose-600!"><svg fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15 17l5-5-5-5M20 12H9M12 21H5V3h7"/></svg></span>
                            <span class="flex-1 text-left">Keluar</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

{{-- ------------------------------------------------------ lembar Notifikasi --}}
<div class="m-sheet lg:hidden" data-sheet="notifications" hidden>
    <div class="m-sheet-backdrop" data-sheet-close></div>
    <section class="m-sheet-panel" role="dialog" aria-modal="true" aria-label="Notifikasi">
        <div class="m-sheet-handle" data-sheet-drag></div>
        <div class="m-sheet-head">
            <h2>Notifikasi</h2>
            <button type="button" class="m-icon-btn" data-sheet-close aria-label="Tutup">
                <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <div class="m-sheet-body">
            @forelse ($notifications as [$label, $detail, $href, $icon, $gradient])
                <a href="{{ $href }}" class="m-notif">
                    <span class="grid size-11 shrink-0 place-items-center rounded-[13px] bg-linear-to-br {{ $gradient }} text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon($icon) }}"/></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[15px] font-semibold text-ink">{{ $label }}</span>
                        <span class="block text-[13px] text-muted">{{ $detail }}</span>
                    </span>
                    <svg class="m-chevron" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>
                </a>
            @empty
                <div class="py-10 text-center">
                    <span class="mx-auto mb-3 grid size-14 place-items-center rounded-full bg-emerald-50 text-emerald-600">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m5 12 5 5L20 7"/></svg>
                    </span>
                    <p class="text-[16px] font-semibold text-ink">Semua beres</p>
                    <p class="text-[14px] text-muted">Tidak ada yang perlu ditindak.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>

<script>
    (() => {
        const root = document.documentElement;
        const mobile = matchMedia('(max-width: 1023.98px)');

        /* ---------------------------------------------------------- lembar */
        let openSheet = null;
        const open = name => {
            const sheet = document.querySelector(`[data-sheet="${name}"]`);
            if (!sheet) return;
            close();
            sheet.hidden = false;
            requestAnimationFrame(() => sheet.classList.add('is-open'));
            root.classList.add('m-locked');
            openSheet = sheet;
        };
        const close = () => {
            if (!openSheet) return;
            const sheet = openSheet;
            openSheet = null;
            sheet.classList.remove('is-open');
            sheet.querySelector('.m-sheet-panel').style.transform = '';
            root.classList.remove('m-locked');
            setTimeout(() => { if (!sheet.classList.contains('is-open')) sheet.hidden = true; }, 260);
        };
        document.querySelectorAll('[data-sheet-open]').forEach(b => b.addEventListener('click', () => open(b.dataset.sheetOpen)));
        document.querySelectorAll('[data-sheet-close]').forEach(b => b.addEventListener('click', close));
        document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
        mobile.addEventListener('change', e => { if (!e.matches) close(); });

        // Tarik pegangan ke bawah untuk menutup.
        document.querySelectorAll('[data-sheet-drag]').forEach(handle => {
            const panel = handle.closest('.m-sheet-panel');
            let startY = null;
            handle.addEventListener('touchstart', e => { startY = e.touches[0].clientY; panel.style.transition = 'none'; }, { passive: true });
            handle.addEventListener('touchmove', e => {
                if (startY === null) return;
                panel.style.transform = `translateY(${Math.max(0, e.touches[0].clientY - startY)}px)`;
            }, { passive: true });
            handle.addEventListener('touchend', e => {
                const moved = e.changedTouches[0].clientY - startY;
                panel.style.transition = '';
                startY = null;
                if (moved > 90) close(); else panel.style.transform = '';
            });
        });

        // Cari di lembar Menu.
        const menuSearch = document.querySelector('[data-menu-search]');
        menuSearch?.addEventListener('input', () => {
            const q = menuSearch.value.trim().toLowerCase();
            let shown = 0;
            document.querySelectorAll('[data-menu-group]').forEach(group => {
                let any = false;
                group.querySelectorAll('[data-menu-item]').forEach(item => {
                    const hit = !q || item.dataset.menuItem.includes(q);
                    item.hidden = !hit;
                    any = any || hit;
                });
                group.hidden = !any;
                shown += any ? 1 : 0;
            });
            document.querySelector('[data-menu-static]').hidden = q !== '';
            document.querySelector('[data-menu-empty]').hidden = shown > 0;
        });

        /* ---------------------------------------------------- tombol kembali */
        // Datang dari halaman panel sendiri → kembali seperti tombol back aplikasi.
        document.querySelector('[data-back]')?.addEventListener('click', e => {
            const ref = document.referrer;
            if (ref && ref.startsWith(location.origin + '/admin') && ref !== location.href && history.length > 1) {
                e.preventDefault();
                history.back();
            }
        });

        /* ---------------------------------------------- tabel → kartu di HP */
        // Label kolom ditempel ke tiap sel; CSS di bawah lg menyusunnya jadi kartu.
        const labelRows = (table, headers) => {
            table.querySelectorAll(':scope > tbody > tr, :scope > tfoot > tr').forEach(row => {
                let col = 0;
                [...row.children].forEach(cell => {
                    const span = cell.colSpan || 1;
                    if (span === 1 && !cell.hasAttribute('data-label')) cell.dataset.label = headers[col] ?? '';
                    if (span > 1) cell.dataset.span = '';
                    col += span;
                });
            });
        };
        document.querySelectorAll('table.table').forEach(table => {
            if (table.dataset.mobile === 'table') return;
            const head = table.querySelector(':scope > thead > tr:last-child');
            if (!head) return;
            const headers = [];
            // Judul kolom gabungan (colspan) hanya menamai sel pertamanya.
            [...head.children].forEach(th => {
                headers.push(th.textContent.replace(/\s+/g, ' ').trim());
                for (let i = 1; i < (th.colSpan || 1); i++) headers.push('');
            });
            // Tabel ringkas (≤ 3 kolom, tanpa isian) sudah muat di layar HP.
            const hasInputs = table.querySelector(':scope > tbody .input, :scope > tbody select');
            if (headers.length <= 3 && !hasInputs && table.dataset.mobile !== 'cards') return;
            table.classList.add('m-cards');
            labelRows(table, headers);
            new MutationObserver(() => labelRows(table, headers)).observe(table, { childList: true, subtree: true });
        });

        // Deret kartu yang digeser: kartu aktif langsung terlihat.
        document.querySelectorAll('.m-scroller [aria-current]').forEach(el => {
            const box = el.closest('.m-scroller');
            box.scrollLeft = el.offsetLeft - box.offsetLeft - 16;
        });

        /* ------------------------------------------- tombol aksi melayang */
        const primary = document.querySelector('[data-page-actions] a.btn-primary');
        if (primary) {
            const fab = document.createElement('a');
            fab.href = primary.href;
            fab.className = 'm-fab lg:hidden';
            const text = primary.textContent.replace(/^\s*\+\s*/, '').trim();
            fab.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg><span></span>';
            fab.querySelector('span').textContent = text;
            fab.setAttribute('aria-label', text);
            document.body.appendChild(fab);
            primary.classList.add('m-fab-source');
            // FAB menyusut jadi bulat saat menggulir ke bawah.
            let lastY = scrollY;
            addEventListener('scroll', () => {
                fab.classList.toggle('is-compact', scrollY > lastY && scrollY > 80);
                lastY = scrollY;
            }, { passive: true });
        }
    })();
</script>
