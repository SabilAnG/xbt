{{--
    Tampilan "aplikasi" untuk layar HP (≤ 767px): tab bar bawah, kartu produk
    2 kolom, filter chip, dan bar beli melekat di halaman detail. Semua aturan
    di dalam media query, jadi tampilan desktop tidak berubah.
--}}
@php
    $tabs = [
        ['Beranda', url('/'), request()->is('/'), 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-9.5Z'],
        ['Produk', route('products.index'), request()->is('products*'), 'M4 5h7v7H4zm9 0h7v7h-7zM4 14h7v6H4zm9 0h7v6h-7z'],
        ['Lacak', route('tracking'), request()->is('tracking'), 'M3 6h11v10H3zm11 4h4l3 3v3h-7M7.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z'],
        ['Info', route('about'), request()->is('about', 'contact', 'workshop'), 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-13h.01M11 12h1v5h1'],
    ];
    $waNumber = setting('whatsapp.primary', config('site.whatsapp.primary'));
    $waText = rawurlencode('Halo '.setting('site.name', 'Hypersonic Speed Tech').', saya mau tanya produk knalpot.');
@endphp

<style>
    .m-tabbar, .m-buybar, .m-search, .m-empty, .m-home { display: none; }

    @media (max-width: 767px) {
        :root { --m-accent: var(--primary-color, #f97316); --m-bar: 64px; }

        body { padding-bottom: calc(var(--m-bar) + env(safe-area-inset-bottom)); -webkit-tap-highlight-color: transparent; }
        body.has-buybar { padding-bottom: calc(76px + env(safe-area-inset-bottom)); }
        #back-to-top { display: none !important; }

        /* -------------------------------------------------- tab bar bawah */
        .m-tabbar {
            display: grid; grid-template-columns: repeat(5, 1fr);
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030;
            height: calc(var(--m-bar) + env(safe-area-inset-bottom)); padding-bottom: env(safe-area-inset-bottom);
            background: rgba(14, 14, 16, .92); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            border-top: 1px solid rgba(255, 255, 255, .08);
        }
        .m-tabbar a {
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px;
            color: rgba(255, 255, 255, .55); font-size: 10.5px; font-weight: 500; letter-spacing: .01em; text-decoration: none;
        }
        .m-tabbar a svg { width: 22px; height: 22px; }
        .m-tabbar a.is-active { color: var(--m-accent); }
        .m-tabbar a.m-tab-wa span.m-tab-wa-icon {
            display: grid; place-items: center; width: 44px; height: 44px; margin-top: -18px; border-radius: 999px;
            background: #25d366; color: #fff; box-shadow: 0 6px 18px -4px rgba(37, 211, 102, .6); border: 3px solid #0e0e10;
        }
        .m-tabbar a.m-tab-wa { color: rgba(255, 255, 255, .75); }
        body.has-buybar .m-tabbar { display: none; }

        /* -------------------------------------------------- header ringkas */
        header #topbar { display: none !important; }
        header .de-flex.sm-pt10 { padding-top: 6px !important; padding-bottom: 6px !important; }
        header .logo-mobile { height: 32px !important; }

        /* ------------------------------------------------ halaman produk */
        .m-products-hero { padding: 76px 0 14px !important; min-height: 0 !important; }
        .m-products-hero .spacer-double { display: none; }
        .m-products-hero h1 { font-size: 26px !important; margin-bottom: 0 !important; }
        .m-products-hero .border-bottom, .m-products-hero .crumb { display: none; }
        .m-products-section { padding-top: 14px !important; padding-bottom: 28px !important; }

        .m-search { display: block; position: relative; margin-bottom: 12px; }
        .m-search input {
            width: 100%; height: 44px; padding: 0 14px 0 42px; border-radius: 12px; font-size: 14px;
            background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .1); color: #fff; outline: none;
        }
        .m-search input:focus { border-color: var(--m-accent); }
        .m-search svg { position: absolute; left: 14px; top: 50%; width: 18px; height: 18px; transform: translateY(-50%); color: rgba(255, 255, 255, .45); }

        .item_filter_group { padding: 0 !important; margin: 0 0 10px !important; background: none !important; border: 0 !important; }
        .item_filter_group h4 { display: none; }
        .item_filter_group .de_form {
            display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: 0 -12px; padding: 0 12px 2px;
            scroll-snap-type: x proximity;
        }
        .item_filter_group .de_form::-webkit-scrollbar { display: none; }
        .item_filter_group .de_checkbox { flex: 0 0 auto; margin: 0 !important; padding: 0 !important; scroll-snap-align: start; }
        .item_filter_group .de_checkbox input { position: absolute; opacity: 0; pointer-events: none; }
        .item_filter_group .de_checkbox label {
            display: inline-flex !important; align-items: center; height: 34px; padding: 0 14px !important; margin: 0 !important;
            border-radius: 999px; font-size: 12.5px; white-space: nowrap; text-transform: none;
            background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .1); color: rgba(255, 255, 255, .8);
        }
        .item_filter_group .de_checkbox label::before, .item_filter_group .de_checkbox label::after { display: none !important; }
        .item_filter_group .de_checkbox input:checked + label { background: var(--m-accent); border-color: var(--m-accent); color: #fff; font-weight: 600; }

        .m-product-grid { --bs-gutter-x: 10px; --bs-gutter-y: 10px; }
        .m-product-grid > [class*='col-'] { flex: 0 0 auto; width: 50%; }
        .m-product-grid .de__pcard {
            height: 100%; display: flex; flex-direction: column; text-align: left !important; padding: 0 !important;
            border-radius: 14px; overflow: hidden; background: rgba(255, 255, 255, .04); border: 1px solid rgba(255, 255, 255, .07);
        }
        .m-product-grid .atr__images { aspect-ratio: 1; overflow: hidden; margin: 0 !important; border-radius: 0 !important; }
        .m-product-grid .atr__images img { width: 100%; height: 100%; object-fit: cover; }
        .m-product-grid .atr__extra-menu { display: none; }
        .m-product-grid .de__pcard h3 {
            font-size: 12.5px !important; line-height: 1.35 !important; margin: 10px 10px 4px !important; min-height: 2.7em;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-transform: none;
        }
        .m-product-grid .atr__main-price { font-size: 15px !important; font-weight: 700; margin: 0 10px !important; color: var(--m-accent); }
        .m-product-grid .m-stock { font-size: 11px !important; margin: 2px 10px 0 !important; opacity: .6 !important; }
        .m-product-grid .de__pcard .btn-main {
            margin: auto 10px 10px !important; padding: 8px 6px !important; font-size: 11.5px !important; border-radius: 10px !important;
            display: flex; justify-content: center; align-items: center; gap: 6px; background: #25d366 !important; color: #fff !important;
        }
        .m-product-grid .de__pcard .btn-main span { padding: 0 !important; }
        .m-empty { text-align: center; padding: 40px 0; opacity: .6; }

        /* ------------------------------------------------- detail produk */
        .m-detail-section { padding-top: 56px !important; }
        .m-detail-section .row.gy-4 { --bs-gutter-y: 0; }
        .m-detail-section .m-detail-info { padding-top: 14px; }
        .m-detail-section > .container { padding-left: 0; padding-right: 0; }
        .m-detail-section .row { --bs-gutter-x: 0; margin: 0; }
        .m-detail-section #sync1 .item img { aspect-ratio: 1; object-fit: cover; }
        .m-detail-section #sync2 { padding: 0 16px; }
        .m-detail-section .m-detail-info, .m-detail-section .m-detail-desc { padding-left: 16px !important; padding-right: 16px !important; }
        .m-detail-section h2.fs-40 { font-size: 22px !important; line-height: 1.3 !important; margin-top: 6px; }
        .m-detail-section h3.fs-32 { font-size: 24px !important; color: var(--m-accent); }
        .m-detail-section #btn-buy-now, .m-detail-section #checkout-contact-list { display: none !important; }
        .m-detail-section .product-title { font-size: 18px; }
        article.product-description h2, article.product-description h3 { font-size: 16px !important; }
        article.product-description p, article.product-description li { font-size: 14px; line-height: 1.6; }

        .m-buybar {
            display: flex; align-items: center; gap: 10px;
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030;
            padding: 10px 14px calc(10px + env(safe-area-inset-bottom));
            background: rgba(14, 14, 16, .95); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            border-top: 1px solid rgba(255, 255, 255, .08);
        }
        .m-buybar .m-buybar-price { flex: 1; min-width: 0; line-height: 1.2; }
        .m-buybar .m-buybar-price small { display: block; font-size: 11px; opacity: .55; }
        .m-buybar .m-buybar-price strong { font-size: 17px; color: #fff; }
        .m-buybar a {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 46px; border-radius: 12px;
            font-size: 14px; font-weight: 600; text-decoration: none;
        }
        .m-buybar .m-buybar-chat { width: 46px; background: rgba(255, 255, 255, .08); color: #fff; border: 1px solid rgba(255, 255, 255, .1); }
        .m-buybar .m-buybar-buy { padding: 0 18px; background: #25d366; color: #fff; box-shadow: 0 6px 18px -6px rgba(37, 211, 102, .7); }

        /* ------------------------------------------------ beranda katalog */
        .m-hide-mobile { display: none !important; }
        .m-home { display: block; position: relative; z-index: 2; padding: 12px 14px 8px; }
        .m-home-search { margin-bottom: 14px; }

        .m-banners {
            display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; gap: 0;
            border-radius: 16px; aspect-ratio: 16 / 8.5;
        }
        .m-banners::-webkit-scrollbar { display: none; }
        .m-banner {
            flex: 0 0 100%; scroll-snap-align: start; position: relative; background-size: cover; background-position: center;
            border-radius: 16px; overflow: hidden; text-decoration: none;
        }
        .m-banner::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(0,0,0,.78), rgba(0,0,0,.15) 70%); }
        .m-banner-text { position: absolute; z-index: 1; left: 16px; bottom: 16px; right: 40%; display: flex; flex-direction: column; gap: 2px; color: #fff; }
        .m-banner-text strong { font-size: 18px; line-height: 1.2; }
        .m-banner-text small { font-size: 12px; opacity: .75; }
        .m-banner-text em { font-style: normal; font-size: 12px; font-weight: 600; color: var(--m-accent); margin-top: 6px; }
        .m-dots { display: flex; justify-content: center; gap: 6px; margin: 10px 0 4px; }
        .m-dots span { width: 6px; height: 6px; border-radius: 999px; background: rgba(255,255,255,.25); transition: all .25s; }
        .m-dots span.is-active { width: 18px; background: var(--m-accent); }

        .m-quick { position: static; height: auto; display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin: 14px 0 6px; }
        .m-quick a { display: flex; flex-direction: column; align-items: center; gap: 6px; font-size: 11px; line-height: 1.2; text-align: center; color: rgba(255,255,255,.8); text-decoration: none; }
        .m-quick a span { display: grid; place-items: center; width: 50px; height: 50px; border-radius: 16px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08); color: var(--m-accent); }
        .m-quick a svg { width: 22px; height: 22px; }

        .m-chips { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: 12px -14px 0; padding: 0 14px; }
        .m-chips::-webkit-scrollbar { display: none; }
        .m-chips a { flex: 0 0 auto; height: 32px; display: inline-flex; align-items: center; padding: 0 14px; border-radius: 999px; font-size: 12.5px; white-space: nowrap; text-decoration: none; color: rgba(255,255,255,.85); background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1); }

        .m-section-head { display: flex; align-items: baseline; justify-content: space-between; margin: 22px 0 10px; }
        .m-section-head h2 { font-size: 17px !important; margin: 0 !important; letter-spacing: 0; }
        .m-section-head a { font-size: 12.5px; font-weight: 600; color: var(--m-accent); text-decoration: none; }
        .m-section-head span { font-size: 12px; opacity: .5; }

        .m-rail { display: flex; gap: 10px; overflow-x: auto; scrollbar-width: none; margin: 0 -14px; padding: 0 14px 4px; scroll-snap-type: x proximity; }
        .m-rail::-webkit-scrollbar { display: none; }
        .m-rail { padding-left: 14px !important; padding-right: 14px !important; scroll-padding-left: 14px; }
        .m-rail .m-card { flex: 0 0 42%; scroll-snap-align: start; }
        .m-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }

        .m-card { display: flex; flex-direction: column; border-radius: 14px; overflow: hidden; text-decoration: none; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); }
        .m-card:active { transform: scale(.98); }
        .m-card-img { position: relative; display: block; aspect-ratio: 1; background: #1a1a1d; }
        .m-card-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .m-card-badge { position: absolute; top: 8px; left: 8px; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 600; background: #16a34a; color: #fff; }
        .m-card-badge.is-out { background: rgba(0,0,0,.65); }
        .m-card-body { display: flex; flex-direction: column; gap: 3px; padding: 9px 10px 11px; }
        .m-card-name { font-size: 12.5px; line-height: 1.35; color: #fff; font-weight: 500; min-height: 2.7em; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .m-card-fit { font-size: 11px; opacity: .5; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .m-card-price { font-size: 14.5px; font-weight: 700; color: var(--m-accent); margin-top: 2px; }

        .m-more { display: flex; justify-content: center; align-items: center; height: 44px; margin-top: 12px; border-radius: 12px; font-size: 13px; font-weight: 600; text-decoration: none; color: #fff; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1); }
        .m-home-empty { text-align: center; opacity: .6; padding: 24px 0; }
        .m-trust { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 22px 0 6px; }
        .m-trust div { padding: 12px 8px; border-radius: 12px; text-align: center; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); }
        .m-trust strong { display: block; font-size: 12.5px; color: var(--m-accent); }
        .m-trust small { display: block; font-size: 10.5px; opacity: .6; line-height: 1.3; margin-top: 2px; }

        footer { padding-top: 40px !important; }
        footer .footer-map iframe { height: 180px; }
    }
</style>

<nav class="m-tabbar" aria-label="Navigasi utama">
    @foreach (array_slice($tabs, 0, 2) as [$label, $href, $active, $icon])
        <a href="{{ $href }}" @class(['is-active' => $active])>
            <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>{{ $label }}
        </a>
    @endforeach
    <a href="https://wa.me/{{ $waNumber }}?text={{ $waText }}" target="_blank" rel="noopener" class="m-tab-wa">
        <span class="m-tab-wa-icon"><i class="fa-brands fa-whatsapp" style="font-size:22px"></i></span>Chat
    </a>
    @foreach (array_slice($tabs, 2) as [$label, $href, $active, $icon])
        <a href="{{ $href }}" @class(['is-active' => $active])>
            <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>{{ $label }}
        </a>
    @endforeach
</nav>

@stack('mobile-bar')
