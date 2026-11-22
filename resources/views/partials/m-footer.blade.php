{{-- Footer versi HP: ringkas, gaya aplikasi. Footer template tetap dipakai di desktop. --}}
@php
    $siteName = setting('site.name', 'Hypersonic Speed Tech');
    $tagline = setting('site.tagline', 'Official Hypersonic Speed Tech');
    $phone = setting('contact.phone', config('site.phone'));
    $email = setting('contact.email', config('site.email'));
    $address = setting('contact.address') ?: 'Jl. Raya Rabak No. 1, RT 01 / RW 05, Kalimanah, Purbalingga, Jawa Tengah 53371';
    $wa = setting('whatsapp.primary', config('site.whatsapp.primary'));
    $socials = array_filter([
        'instagram' => setting('social.instagram'),
        'facebook' => setting('social.facebook'),
        'tiktok' => setting('social.tiktok'),
        'youtube' => setting('social.youtube'),
    ]);
    $actions = [
        ['WhatsApp', 'https://wa.me/'.$wa.'?text='.rawurlencode('Halo '.$siteName.', saya mau tanya produk knalpot.'), 'fa-brands fa-whatsapp', true],
        ['Telepon', 'tel:+'.ltrim((string) $phone, '+'), 'fa-solid fa-phone', false],
        ['Email', 'mailto:'.$email, 'fa-regular fa-envelope', false],
        ['Lokasi', 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address), 'fa-solid fa-location-dot', true],
    ];
    $menu = [
        ['Beranda', url('/')], ['Produk', route('products.index')], ['Lacak Pesanan', route('tracking')],
        ['Workshop', route('workshop')], ['Tentang Kami', route('about')], ['Kontak', route('contact')],
    ];
@endphp

<style>
    .m-footer { display: none; }

    @media (max-width: 767px) {
        footer { display: none !important; }
        .m-footer {
            display: block; padding: 28px 18px 22px; margin-top: 8px;
            background: #0d0d0f; border-top: 1px solid rgba(255, 255, 255, .07); color: rgba(255, 255, 255, .7); font-size: 13px;
        }
        .m-footer-brand img { height: 30px; width: auto; display: block; }
        .m-footer-brand .logo-for-light { display: none; }
        .m-footer-brand p { margin: 10px 0 0; font-size: 13px; line-height: 1.5; color: rgba(255, 255, 255, .55); }

        .m-footer-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 20px 0 16px; }
        .m-footer-actions a {
            display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 12px 4px 10px; border-radius: 14px;
            background: rgba(255, 255, 255, .05); border: 1px solid rgba(255, 255, 255, .07);
            color: rgba(255, 255, 255, .8); font-size: 11px; font-weight: 500; text-decoration: none;
        }
        .m-footer-actions a i { font-size: 18px; color: var(--primary-color, #f97316); }
        .m-footer-actions a.is-wa i { color: #25d366; }

        .m-footer-social { display: flex; gap: 10px; margin-bottom: 14px; }
        .m-footer-social a {
            display: grid; place-items: center; width: 38px; height: 38px; border-radius: 999px;
            background: rgba(255, 255, 255, .06); color: #fff; font-size: 16px; text-decoration: none;
        }

        .m-footer details { border-top: 1px solid rgba(255, 255, 255, .07); }
        .m-footer details:last-of-type { border-bottom: 1px solid rgba(255, 255, 255, .07); }
        .m-footer summary {
            display: flex; align-items: center; justify-content: space-between; height: 48px; list-style: none; cursor: pointer;
            font-size: 14px; font-weight: 600; color: #fff;
        }
        .m-footer summary::-webkit-details-marker { display: none; }
        .m-footer summary::after { content: ''; width: 8px; height: 8px; border-right: 2px solid currentColor; border-bottom: 2px solid currentColor; transform: rotate(45deg) translateY(-3px); opacity: .5; transition: transform .2s; }
        .m-footer details[open] summary::after { transform: rotate(-135deg) translateY(-1px); }
        .m-footer-links { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 12px; padding: 0 0 14px; }
        .m-footer-links a { padding: 7px 0; color: rgba(255, 255, 255, .7); text-decoration: none; font-size: 13.5px; }
        .m-footer-address { padding: 0 0 14px; line-height: 1.6; color: rgba(255, 255, 255, .7); }

        .m-footer-bottom { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 6px 14px; margin-top: 18px; font-size: 11.5px; color: rgba(255, 255, 255, .45); }
        .m-footer-bottom a { color: inherit; text-decoration: none; }
        .m-footer-bottom nav { display: flex; gap: 14px; }

        /* Tema terang */
        html[data-theme='light'] .m-footer { background: #f5f6f8; border-top-color: #e7e9ec; color: #4b5563; }
        html[data-theme='light'] .m-footer-brand .logo-for-dark { display: none; }
        html[data-theme='light'] .m-footer-brand .logo-for-light { display: block; }
        html[data-theme='light'] .m-footer-brand p, html[data-theme='light'] .m-footer-bottom { color: #6b7280; }
        html[data-theme='light'] .m-footer-actions a { background: #fff; border-color: #e7e9ec; color: #374151; }
        html[data-theme='light'] .m-footer-social a { background: #fff; color: #111418; border: 1px solid #e7e9ec; }
        html[data-theme='light'] .m-footer details { border-color: #e3e6ea; }
        html[data-theme='light'] .m-footer summary { color: #111418; }
        html[data-theme='light'] .m-footer-links a, html[data-theme='light'] .m-footer-address { color: #4b5563 !important; }
    }
</style>

<div class="m-footer">
    <div class="m-footer-brand">
        <img class="logo-for-dark" src="{{ asset(setting('site.logo', 'assets/images/logo-white.png')) }}" alt="{{ $siteName }}">
        <img class="logo-for-light" src="{{ asset(setting('site.logo_light', 'assets/images/logo-dark.png')) }}" alt="{{ $siteName }}">
        <p>{{ $tagline }} — knalpot custom & full system, dikerjakan dengan tangan.</p>
    </div>

    <div class="m-footer-actions">
        @foreach ($actions as [$label, $href, $icon, $external])
            <a href="{{ $href }}" @class(['is-wa' => $label === 'WhatsApp']) @if ($external) target="_blank" rel="noopener" @endif>
                <i class="{{ $icon }}"></i>{{ $label }}
            </a>
        @endforeach
    </div>

    @if ($socials)
        <div class="m-footer-social">
            @foreach ($socials as $network => $url)
                <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}"><i class="fa-brands fa-{{ $network === 'facebook' ? 'facebook-f' : $network }}"></i></a>
            @endforeach
        </div>
    @endif

    <details>
        <summary>Menu</summary>
        <div class="m-footer-links">
            @foreach ($menu as [$label, $href])
                <a href="{{ $href }}">{{ $label }}</a>
            @endforeach
        </div>
    </details>
    <details>
        <summary>Alamat workshop</summary>
        <div class="m-footer-address">{{ $address }}</div>
    </details>

    <div class="m-footer-bottom">
        <span>© {{ now()->year }} {{ $siteName }}</span>
        <nav>
            <a href="{{ route('terms-and-conditions') }}">Syarat &amp; Ketentuan</a>
            <a href="{{ route('privacy-policy') }}">Privasi</a>
        </nav>
    </div>
</div>
