{{-- Beranda versi HP: katalog gaya aplikasi e-commerce. Disembunyikan di desktop. --}}
@php
    $latest = $catalog->sortByDesc('id')->take(6);
    // Chip merek motor: hanya merek yang memang muncul di nama/fitment produk.
    $haystack = strtolower($catalog->map(fn ($p) => $p->name.' '.$p->fitment)->implode(' '));
    $brands = collect(['Harley Davidson', 'Yamaha', 'Honda', 'Kawasaki', 'Suzuki', 'BMW', 'Ducati', 'KTM', 'Vespa', 'Mio', 'Nmax', 'Aerox', 'Vario', 'PCX', 'Beat', 'Scoopy'])
        ->filter(fn ($brand) => str_contains($haystack, strtolower($brand)))->values();
    $waNumber = setting('whatsapp.primary', config('site.whatsapp.primary'));
    $quick = [
        ['Semua Produk', route('products.index'), 'M4 5h7v7H4zm9 0h7v7h-7zM4 14h7v6H4zm9 0h7v6h-7z', false],
        ['Lacak Pesanan', route('tracking'), 'M3 6h11v10H3zm11 4h4l3 3v3h-7M7.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z', false],
        ['Workshop', route('workshop'), 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.8 3.8Z', false],
        ['Chat Admin', 'https://wa.me/'.$waNumber.'?text='.rawurlencode(content('mobile.home.wa_message', 'Halo, saya mau tanya produk knalpot.')), 'M7 8h10M7 12h6m-9 8 2.5-3H19a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14Z', true],
    ];
    $bannerDefaults = [
        1 => ['assets/images/header1.png', 'Knalpot Full System', 'Handmade stainless premium'],
        2 => ['assets/images/header2.png', 'Custom Sesuai Motor', 'Konsultasi gratis via WhatsApp'],
        3 => ['assets/images/header3.png', 'Workshop & Detailing', 'Poles & perawatan knalpot'],
    ];
    // Banner diatur di Website → Konten (halaman Home, "HP · Banner …"). Banner tanpa judul & gambar disembunyikan.
    $banners = collect($bannerDefaults)->map(fn ($d, $i) => [
        content("mobile.home.banner.{$i}.image", $d[0]),
        content("mobile.home.banner.{$i}.title", $d[1]),
        content("mobile.home.banner.{$i}.caption", $d[2]),
    ])->filter(fn ($b) => filled($b[0]))->values()->all();
@endphp

<div class="m-home">
    <div class="m-banners" id="m-banners">
        @foreach ($banners as [$image, $title, $caption])
            <a href="{{ route('products.index') }}" class="m-banner" style="background-image: url('{{ asset($image) }}')">
                <span class="m-banner-text">
                    <strong>{{ $title }}</strong>
                    <small>{{ $caption }}</small>
                    <em>Belanja sekarang →</em>
                </span>
            </a>
        @endforeach
    </div>
    <div class="m-dots" id="m-dots">
        @foreach ($banners as $i => $banner)<span @class(['is-active' => $i === 0])></span>@endforeach
    </div>

    <div class="m-quick">
        @foreach ($quick as [$label, $href, $icon, $external])
            <a href="{{ $href }}" @if ($external) target="_blank" rel="noopener" @endif>
                <span><svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg></span>
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if ($brands->isNotEmpty())
        <div class="m-chips">
            @foreach ($brands as $brand)
                <a href="{{ route('products.index', ['q' => $brand]) }}">{{ $brand }}</a>
            @endforeach
        </div>
    @endif

    @if ($latest->isNotEmpty())
        <div class="m-section-head">
            <h2>{{ content('mobile.home.latest_title', 'Produk Terbaru') }}</h2>
            <a href="{{ route('products.index') }}">Lihat semua</a>
        </div>
        <div class="m-rail">
            @foreach ($latest as $product)
                @include('partials.m-product-card', ['product' => $product])
            @endforeach
        </div>
    @endif

    <div class="m-section-head">
        <h2>{{ content('mobile.home.catalog_title', 'Katalog Produk') }}</h2>
        <span>{{ $catalog->count() }} produk</span>
    </div>
    @if ($catalog->isEmpty())
        <p class="m-home-empty">Produk segera hadir.</p>
    @else
        <div class="m-grid">
            @foreach ($catalog->take(8) as $product)
                @include('partials.m-product-card', ['product' => $product])
            @endforeach
        </div>
        @if ($catalog->count() > 8)
            <a href="{{ route('products.index') }}" class="m-more">Lihat {{ $catalog->count() - 8 }} produk lainnya</a>
        @endif
    @endif

    <div class="m-trust">
        <div><strong>Handmade</strong><small>dikerjakan tukang ahli</small></div>
        <div><strong>Stainless</strong><small>bahan premium</small></div>
        <div><strong>Kirim</strong><small>seluruh Indonesia</small></div>
    </div>
</div>

<script>
    (() => {
        // Titik penanda banner mengikuti posisi geser; banner berganti otomatis tiap 4 detik.
        const rail = document.getElementById('m-banners');
        const dots = document.querySelectorAll('#m-dots span');
        if (!rail || !dots.length) return;
        const index = () => Math.round(rail.scrollLeft / rail.clientWidth);
        rail.addEventListener('scroll', () => dots.forEach((d, i) => d.classList.toggle('is-active', i === index())), { passive: true });
        setInterval(() => {
            if (rail.offsetParent === null) return;
            const next = (index() + 1) % dots.length;
            rail.scrollTo({ left: next * rail.clientWidth, behavior: 'smooth' });
        }, 4000);
    })();
</script>
