{{--
    Header gaya aplikasi untuk layar HP (≤ 767px), menggantikan header template.
    Varian:
      - bawaan: logo + tombol tema + menu
      - @section('m-search', 'home'|'products'): baris cari kapsul yang ikut menempel
      - @section('m-back', url) + @section('m-title', judul): tombol kembali + judul (detail produk)
--}}
@php
    $back = trim($__env->yieldContent('m-back'));
    $mTitle = trim($__env->yieldContent('m-title'));
    $searchMode = trim($__env->yieldContent('m-search'));
    $links = [
        ['Beranda', url('/'), 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-9.5Z'],
        ['Produk', route('products.index'), 'M4 5h7v7H4zm9 0h7v7h-7zM4 14h7v6H4zm9 0h7v6h-7z'],
        ['Lacak Pesanan', route('tracking'), 'M3 6h11v10H3zm11 4h4l3 3v3h-7M7.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z'],
        ['Workshop', route('workshop'), 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.8 3.8Z'],
        ['Tentang Kami', route('about'), 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-13h.01M11 12h1v5h1'],
        ['Kontak', route('contact'), 'M4 6h16v12H4zm0 0 8 7 8-7'],
    ];
    $logoDark = asset(setting('site.logo', 'assets/images/logo-white.png'));
    $logoLight = asset(setting('site.logo_light', 'assets/images/logo-dark.png'));
    $waNumber = setting('whatsapp.primary', config('site.whatsapp.primary'));
@endphp

<style>
    .m-appbar, .m-drawer, .m-drawer-backdrop, #m-menu-toggle { display: none; }

    @media (max-width: 767px) {
        :root { --m-appbar-h: 56px; --m-search-h: 54px; }
        header { display: none !important; }
        /* Template sudah menyisakan ruang ±56px untuk header lamanya; cukup tambah baris cari. */
        body.has-m-search { padding-top: var(--m-search-h); }

        .m-appbar {
            display: block; position: fixed; top: 0; left: 0; right: 0; z-index: 1040;
            padding-top: env(safe-area-inset-top);
            background: rgba(14, 14, 16, .86); backdrop-filter: saturate(160%) blur(16px); -webkit-backdrop-filter: saturate(160%) blur(16px);
            border-bottom: 1px solid rgba(255, 255, 255, .07);
            transition: box-shadow .2s;
        }
        .m-appbar.is-scrolled { box-shadow: 0 6px 24px -12px rgba(0, 0, 0, .6); }
        .m-appbar-row { display: flex; align-items: center; gap: 6px; height: var(--m-appbar-h); padding: 0 8px 0 14px; }
        .m-appbar-logo { display: flex; align-items: center; min-width: 0; margin-right: auto; }
        .m-appbar-logo img { height: 28px; width: auto; display: block; }
        .m-appbar-logo .logo-for-light { display: none; }
        .m-appbar-title { flex: 1; min-width: 0; font-size: 15px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0; text-transform: none; }

        .m-icon-btn {
            display: inline-grid; place-items: center; width: 40px; height: 40px; flex: 0 0 auto; padding: 0;
            border-radius: 12px; border: 0; background: transparent; color: #fff; cursor: pointer; text-decoration: none;
        }
        .m-icon-btn:active { background: rgba(255, 255, 255, .1); }
        .m-icon-btn svg { width: 22px; height: 22px; }
        .m-icon-btn.is-back { margin-left: -8px; }
        .m-icon-btn .theme-toggle-moon { display: none; }
        html[data-theme='light'] .m-icon-btn .theme-toggle-sun { display: none; }
        html[data-theme='light'] .m-icon-btn .theme-toggle-moon { display: block; }

        /* Kolom cari kapsul */
        .m-appbar-search { padding: 0 14px 12px; }
        .m-appbar-search form { position: relative; display: flex; align-items: center; height: 42px; margin: 0; }
        .m-appbar-search input {
            width: 100%; height: 42px; margin: 0; padding: 0 44px 0 42px; border-radius: 999px; font-size: 14px;
            background: rgba(255, 255, 255, .08); border: 1px solid transparent; color: #fff; outline: none;
            transition: background .2s, border-color .2s, box-shadow .2s; -webkit-appearance: none; appearance: none;
        }
        .m-appbar-search input::placeholder { color: rgba(255, 255, 255, .45); }
        .m-appbar-search input:focus { background: rgba(255, 255, 255, .12); border-color: var(--primary-color, #f97316); box-shadow: 0 0 0 4px rgba(249, 115, 22, .18); }
        .m-appbar-search input::-webkit-search-cancel-button { display: none; }
        .m-appbar-search .m-search-icon { position: absolute; left: 15px; width: 18px; height: 18px; color: rgba(255, 255, 255, .5); pointer-events: none; }
        .m-appbar-search .m-search-go {
            position: absolute; right: 4px; display: grid; place-items: center; width: 34px; height: 34px; border: 0; border-radius: 999px;
            background: var(--primary-color, #f97316); color: #fff; padding: 0;
        }
        .m-appbar-search .m-search-go svg { width: 16px; height: 16px; }

        /* Menu geser */
        #m-menu-toggle { display: block; position: fixed; opacity: 0; pointer-events: none; }
        .m-drawer-backdrop { display: block; position: fixed; inset: 0; z-index: 1050; background: rgba(0, 0, 0, .5); opacity: 0; pointer-events: none; transition: opacity .25s; }
        .m-drawer {
            display: flex; flex-direction: column; position: fixed; top: 0; right: 0; bottom: 0; z-index: 1060; width: min(84vw, 320px);
            padding: calc(14px + env(safe-area-inset-top)) 16px calc(16px + env(safe-area-inset-bottom));
            background: #141416; border-left: 1px solid rgba(255, 255, 255, .08);
            transform: translateX(100%); transition: transform .28s cubic-bezier(.2, .8, .2, 1);
        }
        #m-menu-toggle:checked ~ .m-drawer-backdrop { opacity: 1; pointer-events: auto; }
        #m-menu-toggle:checked ~ .m-drawer { transform: translateX(0); }
        .m-drawer-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .m-drawer-head img { height: 26px; }
        .m-drawer-head .logo-for-light { display: none; }
        .m-drawer nav { display: flex; flex-direction: column; gap: 2px; }
        .m-drawer nav a {
            display: flex; align-items: center; gap: 12px; height: 48px; padding: 0 12px; border-radius: 12px;
            color: rgba(255, 255, 255, .85); font-size: 15px; font-weight: 500; text-decoration: none;
        }
        .m-drawer nav a svg { width: 20px; height: 20px; color: var(--primary-color, #f97316); }
        .m-drawer nav a.is-active, .m-drawer nav a:active { background: rgba(255, 255, 255, .07); color: #fff; }
        .m-drawer-cta {
            display: flex; align-items: center; justify-content: center; gap: 8px; height: 48px; margin-top: auto; border-radius: 14px;
            background: #25d366; color: #fff !important; font-weight: 600; font-size: 14px; text-decoration: none;
        }

        /* Tema terang */
        html[data-theme='light'] .m-appbar { background: rgba(255, 255, 255, .9); border-bottom-color: #e7e9ec; }
        html[data-theme='light'] .m-appbar.is-scrolled { box-shadow: 0 6px 20px -14px rgba(17, 20, 24, .35); }
        html[data-theme='light'] .m-appbar-logo .logo-for-dark, html[data-theme='light'] .m-drawer-head .logo-for-dark { display: none; }
        html[data-theme='light'] .m-appbar-logo .logo-for-light, html[data-theme='light'] .m-drawer-head .logo-for-light { display: block; }
        html[data-theme='light'] .m-appbar-title, html[data-theme='light'] .m-icon-btn { color: #111418; }
        html[data-theme='light'] .m-icon-btn:active { background: #eef0f3; }
        html[data-theme='light'] .m-appbar-search input { background: #f1f3f5; color: #111418; }
        html[data-theme='light'] .m-appbar-search input::placeholder { color: #8a919c; }
        html[data-theme='light'] .m-appbar-search input:focus { background: #fff; }
        html[data-theme='light'] .m-appbar-search .m-search-icon { color: #8a919c; }
        html[data-theme='light'] .m-drawer { background: #fff; border-left-color: #e7e9ec; }
        html[data-theme='light'] .m-drawer nav a { color: #2b3038; }
        html[data-theme='light'] .m-drawer nav a.is-active, html[data-theme='light'] .m-drawer nav a:active { background: #f3f4f6; color: #111418; }
    }
</style>

<input type="checkbox" id="m-menu-toggle" aria-hidden="true">

<div class="m-appbar" id="m-appbar">
    <div class="m-appbar-row">
        @if ($back)
            <a href="{{ $back }}" class="m-icon-btn is-back" aria-label="Kembali"
                onclick="if (document.referrer.indexOf(location.origin) === 0 && history.length > 1) { history.back(); return false; }">
                <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15 18 9 12l6-6"/></svg>
            </a>
            <span class="m-appbar-title">{{ $mTitle }}</span>
            <button type="button" class="m-icon-btn" aria-label="Bagikan" onclick="if (navigator.share) { navigator.share({ title: document.title, url: location.href }).catch(function () {}); } else if (navigator.clipboard) { navigator.clipboard.writeText(location.href); this.title = 'Link disalin'; }">
                <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 15V3m0 0L8 7m4-4 4 4M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
            </button>
        @else
            <a href="{{ url('/') }}" class="m-appbar-logo" aria-label="Beranda">
                <img class="logo-for-dark" src="{{ $logoDark }}" alt="{{ setting('site.name', config('app.name')) }}">
                <img class="logo-for-light" src="{{ $logoLight }}" alt="{{ setting('site.name', config('app.name')) }}">
            </a>
        @endif
        <button type="button" class="m-icon-btn" onclick="toggleSiteTheme()" aria-label="Ganti tema terang / gelap">
            <svg class="theme-toggle-sun" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            <svg class="theme-toggle-moon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
        </button>
        <label for="m-menu-toggle" class="m-icon-btn" aria-label="Buka menu">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
        </label>
    </div>

    @if ($searchMode)
        <div class="m-appbar-search">
            <form action="{{ route('products.index') }}" method="GET" role="search" @if ($searchMode === 'products') onsubmit="event.preventDefault(); this.querySelector('input').blur();" @endif>
                <svg class="m-search-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15Z"/></svg>
                <input type="search" name="q" @if ($searchMode === 'products') id="m-product-search" @endif value="{{ request('q') }}"
                    placeholder="Cari knalpot, motor, tipe…" autocomplete="off" enterkeyhint="search">
                <button type="submit" class="m-search-go" aria-label="Cari">
                    <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </button>
            </form>
        </div>
    @endif
</div>

<label for="m-menu-toggle" class="m-drawer-backdrop" aria-hidden="true"></label>
<aside class="m-drawer" aria-label="Menu">
    <div class="m-drawer-head">
        <span>
            <img class="logo-for-dark" src="{{ $logoDark }}" alt="">
            <img class="logo-for-light" src="{{ $logoLight }}" alt="">
        </span>
        <label for="m-menu-toggle" class="m-icon-btn" aria-label="Tutup menu">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </label>
    </div>
    <nav>
        @foreach ($links as [$label, $href, $icon])
            <a href="{{ $href }}" @class(['is-active' => rtrim(url()->current(), '/') === rtrim($href, '/')])>
                <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>{{ $label }}
            </a>
        @endforeach
    </nav>
    <a class="m-drawer-cta" href="https://wa.me/{{ $waNumber }}?text={{ rawurlencode('Halo, saya mau tanya produk knalpot.') }}" target="_blank" rel="noopener">
        <i class="fa-brands fa-whatsapp" style="font-size:18px"></i> Chat Admin via WhatsApp
    </a>
</aside>

<script>
    (function () {
        if ({{ $searchMode ? 'true' : 'false' }}) document.body.classList.add('has-m-search');
        var bar = document.getElementById('m-appbar');
        var onScroll = function () { bar.classList.toggle('is-scrolled', window.scrollY > 4); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    })();
</script>
