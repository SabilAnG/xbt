{{--
    Halaman detail produk versi HP, gaya marketplace (Shopee/Tokopedia):
    galeri geser + penghitung, blok harga, info, kartu toko, deskripsi yang
    bisa dibuka, dan rekomendasi. Disembunyikan di desktop.
--}}
@php
    $images = $product->images;
    $stock = $product->availableStock();
    $storeName = setting('site.name', 'Hypersonic Speed Tech');
    $storeLogo = asset(setting('site.logo', 'assets/images/logo-white.png'));
    $storeLogoLight = asset(setting('site.logo_light', 'assets/images/logo-dark.png'));
    $chatUrl = route('redirect.product-checkout', ['product' => $product->slug, 'customer_support' => 1]);
    $infoRows = array_filter([
        'Cocok untuk' => $product->fitment,
        'Kondisi' => 'Baru',
        'Stok' => $product->item ? ($stock > 0 ? qty($stock).' unit' : 'Pre-order') : null,
        'Pengiriman' => 'Seluruh Indonesia',
    ]);
@endphp

<style>
    .m-pdp { display: none; }

    @media (max-width: 767px) {
        .m-pdp {
            --pdp-bg: #0c0c0e; --pdp-card: #17171a; --pdp-line: rgba(255, 255, 255, .07); --pdp-text: #f4f4f6;
            --pdp-soft: rgba(255, 255, 255, .68); --pdp-muted: rgba(255, 255, 255, .46); --pdp-chip: rgba(255, 255, 255, .06);
            --pdp-accent: var(--primary-color, #f97316);
            display: block; background: var(--pdp-bg); padding: 0 0 calc(96px + env(safe-area-inset-bottom));
        }
        .m-pdp-card {
            margin: 12px 12px 0; padding: 16px; border-radius: 20px; background: var(--pdp-card);
            border: 1px solid var(--pdp-line); box-shadow: 0 14px 30px -22px rgba(0, 0, 0, .9);
        }
        .m-pdp-card h3 {
            display: flex; align-items: center; gap: 8px; font-size: 15px !important; font-weight: 600; margin: 0 0 12px !important;
            color: var(--pdp-text); letter-spacing: 0; text-transform: none;
        }
        .m-pdp-card h3::before { content: ''; width: 4px; height: 16px; border-radius: 4px; background: linear-gradient(var(--pdp-accent), #fbbf24); }

        /* Galeri */
        .m-pdp-gallery { position: relative; overflow: hidden; border-radius: 0 0 26px 26px; background: #000; box-shadow: 0 18px 40px -26px rgba(0, 0, 0, 1); }
        .m-pdp-slides { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; aspect-ratio: 1; }
        .m-pdp-slides::-webkit-scrollbar { display: none; }
        .m-pdp-slides img, .m-pdp-slides video { flex: 0 0 100%; width: 100%; height: 100%; object-fit: cover; scroll-snap-align: start; display: block; }
        .m-pdp-slides video { object-fit: contain; background: #000; }
        .m-pdp-gallery::after { content: ''; position: absolute; inset: auto 0 0; height: 90px; pointer-events: none; background: linear-gradient(transparent, rgba(0, 0, 0, .45)); }
        .m-pdp-counter {
            position: absolute; top: 14px; right: 14px; z-index: 1; padding: 4px 11px; border-radius: 999px; font-size: 11.5px; font-weight: 600;
            background: rgba(10, 10, 12, .55); color: #fff; border: 1px solid rgba(255, 255, 255, .14); backdrop-filter: blur(10px);
        }
        .m-pdp-dots { position: absolute; left: 0; right: 0; bottom: 14px; z-index: 1; display: flex; justify-content: center; gap: 6px; }
        .m-pdp-dots span { width: 6px; height: 6px; border-radius: 999px; background: rgba(255, 255, 255, .45); transition: all .25s ease; }
        .m-pdp-dots span.is-active { width: 20px; background: #fff; }

        .m-pdp-thumbs { display: flex; gap: 10px; overflow-x: auto; scrollbar-width: none; padding: 14px 12px 2px; }
        .m-pdp-thumbs::-webkit-scrollbar { display: none; }
        .m-pdp-thumbs button {
            position: relative; flex: 0 0 60px; height: 60px; padding: 0; border-radius: 14px; overflow: hidden; background: var(--pdp-card);
            border: 2px solid transparent; opacity: .55; transition: all .2s ease;
        }
        .m-pdp-thumbs button.is-active { border-color: var(--pdp-accent); opacity: 1; box-shadow: 0 8px 18px -10px var(--pdp-accent); }
        .m-pdp-thumbs img, .m-pdp-thumbs video { width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none; }
        .m-pdp-play { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(0, 0, 0, .35); color: #fff; }
        .m-pdp-play svg { width: 16px; height: 16px; }

        /* Nama, harga, label */
        .m-pdp-name { font-size: 18px !important; line-height: 1.35 !important; font-weight: 600 !important; color: var(--pdp-text); margin: 0 0 10px !important; text-transform: none; letter-spacing: -.01em; }
        .m-pdp-pricing { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; }
        .m-pdp-price-label { display: block; font-size: 11px; font-weight: 500; color: var(--pdp-muted); margin-bottom: 2px; }
        .m-pdp-price {
            font-size: 28px; font-weight: 800; line-height: 1.1; letter-spacing: -.02em;
            background: linear-gradient(90deg, #fb923c, var(--pdp-accent)); -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .m-pdp-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--pdp-line); }
        .m-pdp-tag { display: inline-flex; align-items: center; gap: 5px; height: 28px; padding: 0 12px; border-radius: 999px; font-size: 11.5px; font-weight: 600; }
        .m-pdp-tag i { width: 6px; height: 6px; border-radius: 999px; background: currentColor; display: inline-block; }
        .m-pdp-tag.is-ok { background: rgba(34, 197, 94, .14); color: #4ade80; }
        .m-pdp-tag.is-po { background: rgba(249, 115, 22, .14); color: #fb923c; }
        .m-pdp-tag.is-muted { background: var(--pdp-chip); color: var(--pdp-soft); }

        /* Keunggulan */
        .m-pdp-perks { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
        .m-pdp-perks div { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 4px 2px; text-align: center; font-size: 11px; font-weight: 500; line-height: 1.3; color: var(--pdp-soft); }
        .m-pdp-perks span {
            display: grid; place-items: center; width: 44px; height: 44px; border-radius: 15px; color: var(--pdp-accent);
            background: linear-gradient(145deg, rgba(249, 115, 22, .22), rgba(249, 115, 22, .06)); border: 1px solid rgba(249, 115, 22, .25);
        }
        .m-pdp-perks svg { width: 21px; height: 21px; }

        /* Spesifikasi */
        .m-pdp-info { margin: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .m-pdp-info div { padding: 10px 12px; border-radius: 14px; background: var(--pdp-chip); min-width: 0; }
        .m-pdp-info div.is-wide { grid-column: span 2; }
        .m-pdp-info dt { font-size: 11px; font-weight: 500; color: var(--pdp-muted); margin-bottom: 3px; }
        .m-pdp-info dd { margin: 0; font-size: 13px; font-weight: 600; color: var(--pdp-text); overflow-wrap: anywhere; }

        /* Toko */
        .m-pdp-store { display: flex; align-items: center; gap: 12px; }
        .m-pdp-store-logo {
            position: relative; width: 50px; height: 50px; flex: 0 0 50px; border-radius: 16px; display: grid; place-items: center;
            background: #000; border: 1px solid var(--pdp-line); overflow: hidden;
        }
        .m-pdp-store-logo img { width: 78%; height: auto; }
        .m-pdp-store-logo .logo-for-light { display: none; }
        .m-pdp-store-name { flex: 1; min-width: 0; }
        .m-pdp-store-name strong { display: block; font-size: 14px; font-weight: 600; color: var(--pdp-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .m-pdp-store-name small { display: flex; align-items: center; gap: 6px; margin-top: 2px; font-size: 11.5px; color: var(--pdp-muted); }
        .m-pdp-store-name small i { width: 7px; height: 7px; border-radius: 999px; background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, .2); display: inline-block; }
        .m-pdp-store a {
            flex: 0 0 auto; height: 38px; display: inline-flex; align-items: center; gap: 6px; padding: 0 16px; border-radius: 999px; font-size: 13px; font-weight: 600;
            text-decoration: none; color: #fff; background: linear-gradient(180deg, #fb923c, var(--pdp-accent)); box-shadow: 0 10px 20px -12px var(--pdp-accent);
        }

        /* Deskripsi */
        .m-pdp-desc { position: relative; max-height: 260px; overflow: hidden; font-size: 13.5px; line-height: 1.7; color: var(--pdp-soft); }
        .m-pdp-desc.is-open { max-height: none; }
        .m-pdp-desc:not(.is-open)::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 90px; background: linear-gradient(rgba(23, 23, 26, 0), var(--pdp-card)); }
        .m-pdp-desc h1, .m-pdp-desc h2, .m-pdp-desc h3, .m-pdp-desc h4 { font-size: 14.5px !important; font-weight: 700 !important; margin: 16px 0 6px !important; color: var(--pdp-text); text-decoration: none; }
        .m-pdp-desc > :first-child { margin-top: 0 !important; }
        .m-pdp-desc p, .m-pdp-desc li { font-size: 13.5px; margin: 0 0 6px; color: inherit; }
        .m-pdp-desc ul, .m-pdp-desc ol { padding-left: 18px; margin: 0 0 8px; }
        .m-pdp-desc u { text-decoration: none; }
        .m-pdp-desc a { color: var(--pdp-accent); }
        .m-pdp-more {
            display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; height: 42px; margin-top: 10px; border-radius: 14px;
            border: 1px solid var(--pdp-line); background: var(--pdp-chip); color: var(--pdp-accent); font-size: 13px; font-weight: 600;
        }
        .m-pdp-more svg { transition: transform .25s ease; }

        .m-pdp-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }

        /* Bilah beli di bawah: kaca, proporsional */
        body .m-buybar {
            gap: 8px; padding: 10px 12px calc(10px + env(safe-area-inset-bottom)); border-radius: 22px 22px 0 0;
            background: rgba(16, 16, 19, .82); border-top: 1px solid rgba(255, 255, 255, .08); backdrop-filter: blur(16px);
            box-shadow: 0 -16px 32px -20px rgba(0, 0, 0, .9);
        }
        body .m-buybar a, body .m-buybar .m-buybar-cart { height: 50px; border-radius: 16px; font-size: 14px; font-weight: 600; }
        body .m-buybar .m-buybar-chat { flex: 0 0 50px; width: 50px; background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .1); color: #fff; }
        body .m-buybar .m-buybar-cart { flex: 1 1 0; border: 1.5px solid var(--m-accent); background: rgba(249, 115, 22, .08); color: var(--m-accent); }
        body .m-buybar .m-buybar-buy { flex: 1.25 1 0; background: linear-gradient(180deg, #34e07a, #1fb85a); box-shadow: 0 12px 22px -12px rgba(37, 211, 102, .9); }

        /* Tema terang */
        html[data-theme='light'] .m-pdp {
            --pdp-bg: #f3f4f7; --pdp-card: #fff; --pdp-line: #eceef2; --pdp-text: #14171c; --pdp-soft: #4b5260; --pdp-muted: #8a909c; --pdp-chip: #f5f6f8;
        }
        html[data-theme='light'] .m-pdp-card { box-shadow: 0 14px 30px -24px rgba(20, 24, 40, .35); }
        html[data-theme='light'] .m-pdp-gallery { background: #e9ebef; box-shadow: 0 18px 40px -28px rgba(20, 24, 40, .5); }
        html[data-theme='light'] .m-pdp-desc:not(.is-open)::after { background: linear-gradient(rgba(255, 255, 255, 0), #fff); }
        html[data-theme='light'] .m-pdp-desc, html[data-theme='light'] .m-pdp-desc p, html[data-theme='light'] .m-pdp-desc li { color: #4b5260 !important; }
        html[data-theme='light'] .m-pdp-desc h1, html[data-theme='light'] .m-pdp-desc h2, html[data-theme='light'] .m-pdp-desc h3,
        html[data-theme='light'] .m-pdp-name, html[data-theme='light'] .m-pdp-card h3 { color: #14171c !important; }
        html[data-theme='light'] .m-pdp-tag.is-ok { background: #dcfce7; color: #15803d; }
        html[data-theme='light'] .m-pdp-tag.is-po { background: #ffedd5; color: #c2410c; }
        html[data-theme='light'] .m-pdp-store-logo { background: #fff; }
        html[data-theme='light'] .m-pdp-store-logo .logo-for-dark { display: none; }
        html[data-theme='light'] .m-pdp-store-logo .logo-for-light { display: block; }
        html[data-theme='light'] body .m-buybar { background: rgba(255, 255, 255, .86); border-top-color: #eceef2; box-shadow: 0 -16px 32px -22px rgba(20, 24, 40, .35); }
        html[data-theme='light'] body .m-buybar .m-buybar-chat { background: #f3f4f7; border-color: #e5e7eb; color: #14171c; }
    }
</style>

<div class="m-pdp">
    <div class="m-pdp-gallery">
        <div class="m-pdp-slides" id="m-pdp-slides">
            @forelse ($images as $image)
                @if ($image->isVideo())
                    <video controls playsinline preload="metadata" aria-label="{{ $product->name }} — video">
                        <source src="{{ asset($image->path) }}#t=0.1" type="{{ $image->videoMime() }}">
                    </video>
                @else
                    <img src="{{ asset($image->path) }}" alt="{{ $product->name }} — foto {{ $loop->iteration }}" @if (! $loop->first) loading="lazy" @endif>
                @endif
            @empty
                <img src="{{ asset('assets/images/workshop/fallback-1000x1000.webp') }}" alt="{{ $product->name }}">
            @endforelse
        </div>
        @if ($images->count() > 1)
            <span class="m-pdp-counter"><span id="m-pdp-index">1</span>/{{ $images->count() }}</span>
            <div class="m-pdp-dots" id="m-pdp-dots">
                @foreach ($images as $image)<span @class(['is-active' => $loop->first])></span>@endforeach
            </div>
        @endif
    </div>
    @if ($images->count() > 1)
        <div class="m-pdp-thumbs" id="m-pdp-thumbs">
            @foreach ($images as $image)
                <button type="button" @class(['is-active' => $loop->first]) data-index="{{ $loop->index }}" aria-label="{{ $image->isVideo() ? 'Video' : 'Foto' }} {{ $loop->iteration }}">
                    @if ($image->isVideo())
                        <video src="{{ asset($image->path) }}#t=0.5" muted playsinline preload="metadata"></video>
                        <span class="m-pdp-play"><svg fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
                    @else
                        <img src="{{ asset($image->thumb()) }}" alt="" loading="lazy">
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    <div class="m-pdp-card">
        <h1 class="m-pdp-name">{{ $product->name }}</h1>
        <div class="m-pdp-pricing">
            <div>
                <span class="m-pdp-price-label">Harga</span>
                <div class="m-pdp-price">{{ $product->formattedPrice() }}</div>
            </div>
        </div>
        <div class="m-pdp-tags">
            @if ($product->item)
                <span @class(['m-pdp-tag', 'is-ok' => $stock > 0, 'is-po' => $stock <= 0])><i></i>{{ $stock > 0 ? 'Ready stock' : 'Pre-order' }}</span>
            @endif
            <span class="m-pdp-tag is-muted">Handmade</span>
            @if ($product->fitment)<span class="m-pdp-tag is-muted">{{ \Illuminate\Support\Str::limit($product->fitment, 28) }}</span>@endif
        </div>
    </div>

    <div class="m-pdp-card">
        <div class="m-pdp-perks">
            <div><span><svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Zm-3 9 2 2 4-4"/></svg></span>Dibuat tukang ahli</div>
            <div><span><svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 6h11v10H3zm11 4h4l3 3v3h-7M7.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg></span>Kirim seluruh Indonesia</div>
            <div><span><svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 8h10M7 12h6m-9 8 2.5-3H19a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14Z"/></svg></span>Konsultasi gratis</div>
        </div>
    </div>

    <div class="m-pdp-card">
        <h3>Spesifikasi</h3>
        <dl class="m-pdp-info">
            @foreach ($infoRows as $label => $value)
                <div @class(['is-wide' => $label === 'Cocok untuk'])><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    </div>

    <div class="m-pdp-card">
        <div class="m-pdp-store">
            <span class="m-pdp-store-logo">
                <img class="logo-for-dark" src="{{ $storeLogo }}" alt="">
                <img class="logo-for-light" src="{{ $storeLogoLight }}" alt="">
            </span>
            <span class="m-pdp-store-name">
                <strong>{{ $storeName }}</strong>
                <small><i></i>Online · balas via WhatsApp</small>
            </span>
            <a href="{{ $chatUrl }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>Chat</a>
        </div>
    </div>

    @if (filled(strip_tags((string) $product->description)))
        <div class="m-pdp-card">
            <h3>Deskripsi Produk</h3>
            <div class="m-pdp-desc" id="m-pdp-desc">{!! $product->description !!}</div>
            <button type="button" class="m-pdp-more" id="m-pdp-more">Lihat selengkapnya
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
        </div>
    @endif

    @if ($others->isNotEmpty())
        <div class="m-pdp-card">
            <h3>Produk lainnya</h3>
            <div class="m-pdp-grid">
                @foreach ($others as $other)
                    @include('partials.m-product-card', ['product' => $other])
                @endforeach
            </div>
        </div>
    @endif
</div>

<script>
    (function () {
        var slides = document.getElementById('m-pdp-slides');
        var index = document.getElementById('m-pdp-index');
        var thumbs = document.querySelectorAll('#m-pdp-thumbs button');
        if (slides) {
            slides.addEventListener('scroll', function () {
                var i = Math.round(slides.scrollLeft / slides.clientWidth);
                // Video yang digeser keluar layar dihentikan.
                slides.querySelectorAll('video').forEach(function (v, n) { if (!v.paused && v.parentNode.children[i] !== v) v.pause(); });
                if (index) index.textContent = i + 1;
                document.querySelectorAll('#m-pdp-dots span').forEach(function (d, n) { d.classList.toggle('is-active', n === i); });
                thumbs.forEach(function (t, n) { t.classList.toggle('is-active', n === i); });
            }, { passive: true });
            thumbs.forEach(function (t) {
                t.addEventListener('click', function () { slides.scrollTo({ left: t.dataset.index * slides.clientWidth, behavior: 'smooth' }); });
            });
        }
        var desc = document.getElementById('m-pdp-desc');
        var more = document.getElementById('m-pdp-more');
        if (desc && more) {
            if (desc.scrollHeight <= 270) { more.style.display = 'none'; desc.classList.add('is-open'); }
            more.addEventListener('click', function () {
                var open = desc.classList.toggle('is-open');
                more.firstChild.textContent = open ? 'Tutup ' : 'Lihat selengkapnya ';
                more.querySelector('svg').style.transform = open ? 'rotate(180deg)' : '';
            });
        }
    })();
</script>
