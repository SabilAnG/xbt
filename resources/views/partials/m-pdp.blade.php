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
        .m-pdp { display: block; background: #0b0b0c; padding-bottom: 8px; }
        .m-pdp-block { background: #151517; padding: 14px 16px; margin-bottom: 8px; }
        .m-pdp-block h3 { font-size: 15px !important; font-weight: 600; margin: 0 0 10px !important; color: #fff; letter-spacing: 0; text-transform: none; }

        .m-pdp-gallery { position: relative; background: #000; }
        .m-pdp-slides { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; aspect-ratio: 1; }
        .m-pdp-slides::-webkit-scrollbar { display: none; }
        .m-pdp-slides img, .m-pdp-slides video { flex: 0 0 100%; width: 100%; height: 100%; object-fit: cover; scroll-snap-align: start; display: block; }
        .m-pdp-slides video { object-fit: contain; background: #000; }
        .m-pdp-thumbs button { position: relative; }
        .m-pdp-thumbs video { width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none; }
        .m-pdp-play { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(0, 0, 0, .35); color: #fff; }
        .m-pdp-play svg { width: 16px; height: 16px; }
        .m-pdp-counter {
            position: absolute; right: 12px; bottom: 12px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600;
            background: rgba(0, 0, 0, .55); color: #fff; backdrop-filter: blur(6px);
        }
        .m-pdp-thumbs { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; padding: 10px 16px; background: #151517; }
        .m-pdp-thumbs::-webkit-scrollbar { display: none; }
        .m-pdp-thumbs button { flex: 0 0 52px; height: 52px; padding: 0; border-radius: 8px; overflow: hidden; border: 2px solid transparent; background: none; opacity: .6; }
        .m-pdp-thumbs button.is-active { border-color: var(--primary-color, #f97316); opacity: 1; }
        .m-pdp-thumbs img { width: 100%; height: 100%; object-fit: cover; display: block; }

        .m-pdp-price { font-size: 24px; font-weight: 700; color: var(--primary-color, #f97316); line-height: 1.2; }
        .m-pdp-name { font-size: 16px !important; line-height: 1.4 !important; font-weight: 500 !important; color: #fff; margin: 6px 0 10px !important; text-transform: none; letter-spacing: 0; }
        .m-pdp-tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .m-pdp-tag { display: inline-flex; align-items: center; gap: 4px; height: 24px; padding: 0 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; }
        .m-pdp-tag.is-ok { background: rgba(22, 163, 74, .15); color: #4ade80; }
        .m-pdp-tag.is-po { background: rgba(249, 115, 22, .15); color: #fb923c; }
        .m-pdp-tag.is-muted { background: rgba(255, 255, 255, .07); color: rgba(255, 255, 255, .7); }

        .m-pdp-perks { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .m-pdp-perks div { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 10px 4px; border-radius: 10px; background: rgba(255, 255, 255, .04); text-align: center; font-size: 11px; line-height: 1.3; color: rgba(255, 255, 255, .75); }
        .m-pdp-perks svg { width: 20px; height: 20px; color: var(--primary-color, #f97316); }

        .m-pdp-info { margin: 0; }
        .m-pdp-info div { display: flex; gap: 12px; padding: 9px 0; border-bottom: 1px solid rgba(255, 255, 255, .06); font-size: 13px; }
        .m-pdp-info div:last-child { border-bottom: 0; }
        .m-pdp-info dt { flex: 0 0 104px; font-weight: 400; color: rgba(255, 255, 255, .5); }
        .m-pdp-info dd { flex: 1; margin: 0; color: #fff; }

        .m-pdp-store { display: flex; align-items: center; gap: 12px; }
        .m-pdp-store-logo { width: 48px; height: 48px; flex: 0 0 48px; border-radius: 999px; display: grid; place-items: center; background: #000; border: 1px solid rgba(255, 255, 255, .1); overflow: hidden; }
        .m-pdp-store-logo img { width: 80%; height: auto; }
        .m-pdp-store-logo .logo-for-light { display: none; }
        .m-pdp-store-name { flex: 1; min-width: 0; }
        .m-pdp-store-name strong { display: block; font-size: 14px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .m-pdp-store-name small { display: flex; align-items: center; gap: 4px; font-size: 11.5px; color: rgba(255, 255, 255, .55); }
        .m-pdp-store-name small i { width: 7px; height: 7px; border-radius: 999px; background: #22c55e; display: inline-block; }
        .m-pdp-store a { flex: 0 0 auto; height: 34px; display: inline-flex; align-items: center; padding: 0 14px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid var(--primary-color, #f97316); color: var(--primary-color, #f97316); }

        .m-pdp-desc { position: relative; max-height: 220px; overflow: hidden; font-size: 13.5px; line-height: 1.65; color: rgba(255, 255, 255, .8); }
        .m-pdp-desc.is-open { max-height: none; }
        .m-pdp-desc:not(.is-open)::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 80px; background: linear-gradient(rgba(21, 21, 23, 0), #151517); }
        .m-pdp-desc h2, .m-pdp-desc h3 { font-size: 14px !important; font-weight: 700 !important; margin: 12px 0 6px !important; color: #fff; text-decoration: none; }
        .m-pdp-desc p, .m-pdp-desc li { font-size: 13.5px; margin: 0 0 6px; color: inherit; }
        .m-pdp-desc u { text-decoration: none; }
        .m-pdp-more { display: flex; align-items: center; justify-content: center; gap: 4px; width: 100%; height: 40px; margin-top: 6px; border: 0; background: none; color: var(--primary-color, #f97316); font-size: 13px; font-weight: 600; }

        .m-pdp-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }

        /* Tema terang */
        html[data-theme='light'] .m-pdp { background: #f1f3f5; }
        html[data-theme='light'] .m-pdp-block, html[data-theme='light'] .m-pdp-thumbs { background: #fff; }
        html[data-theme='light'] .m-pdp-gallery { background: #f1f3f5; }
        html[data-theme='light'] .m-pdp-block h3, html[data-theme='light'] .m-pdp-name,
        html[data-theme='light'] .m-pdp-info dd, html[data-theme='light'] .m-pdp-store-name strong,
        html[data-theme='light'] .m-pdp-desc h2, html[data-theme='light'] .m-pdp-desc h3 { color: #111418 !important; }
        html[data-theme='light'] .m-pdp-info dt, html[data-theme='light'] .m-pdp-store-name small { color: #6b7280; }
        html[data-theme='light'] .m-pdp-info div { border-bottom-color: #eef0f3; }
        html[data-theme='light'] .m-pdp-desc { color: #3f4652 !important; }
        html[data-theme='light'] .m-pdp-desc p, html[data-theme='light'] .m-pdp-desc li { color: #3f4652 !important; }
        html[data-theme='light'] .m-pdp-desc:not(.is-open)::after { background: linear-gradient(rgba(255, 255, 255, 0), #fff); }
        html[data-theme='light'] .m-pdp-perks div { background: #f6f7f9; color: #3f4652; }
        html[data-theme='light'] .m-pdp-tag.is-muted { background: #f1f3f5; color: #4b5563; }
        html[data-theme='light'] .m-pdp-tag.is-ok { background: #dcfce7; color: #15803d; }
        html[data-theme='light'] .m-pdp-tag.is-po { background: #ffedd5; color: #c2410c; }
        html[data-theme='light'] .m-pdp-store-logo { background: #fff; border-color: #e7e9ec; }
        html[data-theme='light'] .m-pdp-store-logo .logo-for-dark { display: none; }
        html[data-theme='light'] .m-pdp-store-logo .logo-for-light { display: block; }
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

    <div class="m-pdp-block">
        <div class="m-pdp-price">{{ $product->formattedPrice() }}</div>
        <h1 class="m-pdp-name">{{ $product->name }}</h1>
        <div class="m-pdp-tags">
            @if ($product->item)
                <span @class(['m-pdp-tag', 'is-ok' => $stock > 0, 'is-po' => $stock <= 0])>{{ $stock > 0 ? 'Ready stock' : 'Pre-order' }}</span>
            @endif
            <span class="m-pdp-tag is-muted">Handmade</span>
            @if ($product->fitment)<span class="m-pdp-tag is-muted">{{ \Illuminate\Support\Str::limit($product->fitment, 28) }}</span>@endif
        </div>
    </div>

    <div class="m-pdp-block">
        <div class="m-pdp-perks">
            <div><svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Zm-3 9 2 2 4-4"/></svg>Dibuat tukang ahli</div>
            <div><svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 6h11v10H3zm11 4h4l3 3v3h-7M7.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg>Kirim seluruh Indonesia</div>
            <div><svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 8h10M7 12h6m-9 8 2.5-3H19a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14Z"/></svg>Konsultasi gratis</div>
        </div>
    </div>

    <div class="m-pdp-block">
        <h3>Spesifikasi</h3>
        <dl class="m-pdp-info">
            @foreach ($infoRows as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    </div>

    <div class="m-pdp-block">
        <div class="m-pdp-store">
            <span class="m-pdp-store-logo">
                <img class="logo-for-dark" src="{{ $storeLogo }}" alt="">
                <img class="logo-for-light" src="{{ $storeLogoLight }}" alt="">
            </span>
            <span class="m-pdp-store-name">
                <strong>{{ $storeName }}</strong>
                <small><i></i>Online · balas via WhatsApp</small>
            </span>
            <a href="{{ $chatUrl }}" target="_blank" rel="noopener">Chat</a>
        </div>
    </div>

    @if (filled(strip_tags((string) $product->description)))
        <div class="m-pdp-block">
            <h3>Deskripsi Produk</h3>
            <div class="m-pdp-desc" id="m-pdp-desc">{!! $product->description !!}</div>
            <button type="button" class="m-pdp-more" id="m-pdp-more">Lihat selengkapnya
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
        </div>
    @endif

    @if ($others->isNotEmpty())
        <div class="m-pdp-block">
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
                thumbs.forEach(function (t, n) { t.classList.toggle('is-active', n === i); });
            }, { passive: true });
            thumbs.forEach(function (t) {
                t.addEventListener('click', function () { slides.scrollTo({ left: t.dataset.index * slides.clientWidth, behavior: 'smooth' }); });
            });
        }
        var desc = document.getElementById('m-pdp-desc');
        var more = document.getElementById('m-pdp-more');
        if (desc && more) {
            if (desc.scrollHeight <= 230) { more.style.display = 'none'; desc.classList.add('is-open'); }
            more.addEventListener('click', function () {
                var open = desc.classList.toggle('is-open');
                more.firstChild.textContent = open ? 'Tutup ' : 'Lihat selengkapnya ';
                more.querySelector('svg').style.transform = open ? 'rotate(180deg)' : '';
            });
        }
    })();
</script>
