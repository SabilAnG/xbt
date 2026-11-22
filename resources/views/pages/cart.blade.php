@extends('layouts.app')

@section('title', 'Keranjang')
@section('m-back', route('products.index', [], false))
@section('m-title', 'Keranjang')
@section('m-noshare', '1')
@section('body-class', 'has-buybar')

@php
    $siteName = setting('site.name', 'Hypersonic Speed Tech');
    $wa = setting('whatsapp.primary', config('site.whatsapp.primary'));
@endphp

@push('head')
<style>
    .cart-page { padding: 140px 0 60px; min-height: 70vh; }
    .cart-page h1 { font-size: 32px; margin-bottom: 6px; }
    .cart-sub { opacity: .6; margin-bottom: 24px; }
    .cart-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 24px; align-items: start; }
    .cart-list { display: flex; flex-direction: column; gap: 12px; }
    .cart-item { display: grid; grid-template-columns: 88px minmax(0, 1fr) auto; gap: 14px; align-items: center; padding: 12px; border-radius: 14px; background: rgba(255, 255, 255, .04); border: 1px solid rgba(255, 255, 255, .08); }
    .cart-item img { width: 88px; height: 88px; object-fit: cover; border-radius: 10px; background: #000; }
    .cart-item-name { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 14px; font-weight: 500; line-height: 1.4; color: inherit; text-decoration: none; }
    .cart-item-price { margin-top: 4px; font-weight: 700; color: var(--primary-color, #e65200); }
    .cart-item-side { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
    .cart-qty { display: inline-flex; align-items: center; border-radius: 10px; border: 1px solid rgba(255, 255, 255, .15); overflow: hidden; }
    .cart-qty button { width: 32px; height: 32px; border: 0; background: transparent; color: inherit; font-size: 18px; line-height: 1; }
    .cart-qty span { min-width: 28px; text-align: center; font-weight: 600; font-size: 14px; }
    .cart-remove { border: 0; background: none; color: inherit; opacity: .55; font-size: 12.5px; padding: 0; }
    .cart-remove:hover { opacity: 1; color: #ef4444; }
    .cart-summary { position: sticky; top: 120px; padding: 18px; border-radius: 16px; background: rgba(255, 255, 255, .04); border: 1px solid rgba(255, 255, 255, .08); }
    .cart-summary h2 { font-size: 16px; margin: 0 0 12px; }
    .cart-row { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; font-size: 14px; }
    .cart-row.total { margin-top: 6px; padding-top: 12px; border-top: 1px solid rgba(255, 255, 255, .1); font-size: 16px; font-weight: 700; }
    .cart-row.total span:last-child { color: var(--primary-color, #e65200); }
    .cart-checkout { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; height: 48px; margin-top: 14px; border: 0; border-radius: 12px; background: #25d366; color: #fff; font-weight: 600; font-size: 15px; }
    .cart-note { margin-top: 10px; font-size: 12px; opacity: .55; line-height: 1.5; }
    .cart-empty { text-align: center; padding: 60px 20px; }
    .cart-empty svg { width: 64px; height: 64px; opacity: .35; margin-bottom: 12px; }
    .cart-empty a { display: inline-flex; align-items: center; height: 44px; padding: 0 22px; margin-top: 16px; border-radius: 12px; background: var(--primary-color, #e65200); color: #fff !important; font-weight: 600; text-decoration: none; }
    .cart-mobile-bar { display: none; }

    html[data-theme='light'] .cart-item, html[data-theme='light'] .cart-summary { background: #fff; border-color: #e5e7eb; box-shadow: 0 1px 3px rgba(17, 20, 24, .05); }
    html[data-theme='light'] .cart-qty { border-color: #d1d5db; }
    html[data-theme='light'] .cart-row.total { border-top-color: #e5e7eb; }
    html[data-theme='light'] .cart-page { background: #f5f6f8; }

    @media (max-width: 991px) { .cart-layout { grid-template-columns: 1fr; } .cart-summary { position: static; } }
    @media (max-width: 767px) {
        .cart-page { padding: 14px 0 20px; min-height: 0; }
        .cart-page h1, .cart-sub { display: none; }
        .cart-item { grid-template-columns: 72px minmax(0, 1fr); gap: 12px; padding: 10px; }
        .cart-item img { width: 72px; height: 72px; }
        .cart-item-side { grid-column: 2; flex-direction: row; align-items: center; justify-content: space-between; }
        .cart-summary .cart-checkout, .cart-summary .cart-row.total { display: none; }
        .cart-mobile-bar {
            display: flex; align-items: center; gap: 12px; position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030;
            padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); background: rgba(14, 14, 16, .96); border-top: 1px solid rgba(255, 255, 255, .08);
        }
        .cart-mobile-bar div { flex: 1; min-width: 0; line-height: 1.2; }
        .cart-mobile-bar small { display: block; font-size: 11px; opacity: .55; }
        .cart-mobile-bar strong { font-size: 17px; color: var(--primary-color, #f97316); }
        .cart-mobile-bar .cart-checkout { width: auto; margin: 0; padding: 0 18px; height: 46px; }
        html[data-theme='light'] .cart-mobile-bar { background: rgba(255, 255, 255, .97); border-top-color: #e5e7eb; }
        html[data-theme='light'] .cart-mobile-bar strong { color: var(--primary-color, #e65200); }
    }
</style>
@endpush

@section('content')
<div class="no-bottom no-top" id="content">
    <section class="cart-page">
        <div class="container">
            <h1>Keranjang</h1>
            <p class="cart-sub">Periksa pesanan Anda, lalu kirim ke admin lewat WhatsApp.</p>
            <div id="cart-root"></div>
            <noscript><p>Aktifkan JavaScript untuk memakai keranjang.</p></noscript>
        </div>
    </section>
</div>
@endsection

@push('mobile-bar')
    <div class="cart-mobile-bar" id="cart-mobile-bar" hidden>
        <div><small>Total</small><strong id="cart-mobile-total">—</strong></div>
        <button type="button" class="cart-checkout" data-cart-checkout><i class="fa-brands fa-whatsapp"></i> Checkout</button>
    </div>
@endpush

@push('scripts')
<script>
    (function () {
        var SITE = @json($siteName);
        var WA = @json((string) $wa);
        var root = document.getElementById('cart-root');
        var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };

        var totals = function (items) {
            var byCurrency = {};
            items.forEach(function (i) { byCurrency[i.currency] = (byCurrency[i.currency] || 0) + i.price * i.qty; });
            return Object.keys(byCurrency).map(function (c) { return XbtCart.format(byCurrency[c], c); }).join(' + ');
        };

        var render = function () {
            var items = XbtCart.items();
            var bar = document.getElementById('cart-mobile-bar');
            // Tab bar bawah tampil saat keranjang kosong; bar checkout saat ada isi.
            document.body.classList.toggle('has-buybar', items.length > 0);
            if (!items.length) {
                if (bar) bar.hidden = true;
                root.innerHTML = '<div class="cart-empty">'
                    + '<svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6.2M9 20.5a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Zm8 0a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Z"/></svg>'
                    + '<h3>Keranjang masih kosong</h3><p style="opacity:.6">Yuk pilih knalpot yang cocok untuk motormu.</p>'
                    + '<a href="{{ route('products.index', [], false) }}">Lihat produk</a></div>';
                return;
            }
            var count = items.reduce(function (n, i) { return n + i.qty; }, 0);
            var list = items.map(function (i) {
                return '<div class="cart-item">'
                    + '<a href="' + esc(i.url) + '"><img src="' + esc(i.image || '') + '" alt=""></a>'
                    + '<div><a class="cart-item-name" href="' + esc(i.url) + '">' + esc(i.name) + '</a>'
                    + '<div class="cart-item-price">' + XbtCart.format(i.price, i.currency) + '</div></div>'
                    + '<div class="cart-item-side">'
                    + '<span class="cart-qty"><button type="button" data-qty="-1" data-slug="' + esc(i.slug) + '" aria-label="Kurangi">−</button><span>' + i.qty + '</span>'
                    + '<button type="button" data-qty="1" data-slug="' + esc(i.slug) + '" aria-label="Tambah">+</button></span>'
                    + '<button type="button" class="cart-remove" data-remove="' + esc(i.slug) + '">Hapus</button>'
                    + '</div></div>';
            }).join('');
            var total = totals(items);
            root.innerHTML = '<div class="cart-layout"><div class="cart-list">' + list + '</div>'
                + '<aside class="cart-summary"><h2>Ringkasan pesanan</h2>'
                + '<div class="cart-row"><span>Jumlah barang</span><span>' + count + '</span></div>'
                + '<div class="cart-row"><span>Ongkir</span><span>dihitung admin</span></div>'
                + '<div class="cart-row total"><span>Total</span><span>' + total + '</span></div>'
                + '<button type="button" class="cart-checkout" data-cart-checkout><i class="fa-brands fa-whatsapp"></i> Checkout via WhatsApp</button>'
                + '<p class="cart-note">Pesanan dikirim ke admin lewat WhatsApp untuk konfirmasi stok, ongkir, dan pembayaran.</p>'
                + '<button type="button" class="cart-remove" data-clear style="margin-top:8px">Kosongkan keranjang</button></aside></div>';
            if (bar) { bar.hidden = false; document.getElementById('cart-mobile-total').textContent = total; }
        };

        document.addEventListener('click', function (e) {
            var t = e.target.closest('[data-qty],[data-remove],[data-clear],[data-cart-checkout]');
            if (!t) return;
            var items = XbtCart.items();
            if (t.dataset.qty) {
                var item = items.find(function (i) { return i.slug === t.dataset.slug; });
                if (item) XbtCart.setQty(item.slug, item.qty + Number(t.dataset.qty));
            } else if (t.dataset.remove) {
                XbtCart.remove(t.dataset.remove);
            } else if (t.hasAttribute('data-clear')) {
                if (confirm('Kosongkan keranjang?')) XbtCart.clear();
            } else if (t.hasAttribute('data-cart-checkout') && items.length) {
                var lines = items.map(function (i, n) {
                    return (n + 1) + '. ' + i.name + ' x' + i.qty + ' = ' + XbtCart.format(i.price * i.qty, i.currency) + '\n   ' + location.origin + i.url;
                });
                var text = 'Halo ' + SITE + ', saya mau pesan:\n\n' + lines.join('\n') + '\n\nTotal: ' + totals(items) + '\n\nNama:\nAlamat pengiriman:\nTipe motor:';
                window.open('https://wa.me/' + WA + '?text=' + encodeURIComponent(text), '_blank');
            }
        });

        document.addEventListener('cart:changed', render);
        render();
    })();
</script>
@endpush
