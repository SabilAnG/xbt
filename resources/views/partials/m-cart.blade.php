{{--
    Keranjang belanja sisi browser (localStorage). Belum ada pembayaran online:
    checkout mengirim daftar pesanan ke WhatsApp admin.
    Tombol: [data-cart-add='{"slug":…,"name":…,"price":…,"currency":…,"image":…}']
    Badge:  [data-cart-count]
--}}
<style>
    .cart-toast {
        position: fixed; left: 50%; bottom: calc(84px + env(safe-area-inset-bottom)); z-index: 2000;
        display: flex; align-items: center; gap: 10px; max-width: calc(100vw - 32px); padding: 10px 12px 10px 14px;
        border-radius: 14px; background: #111418; color: #fff; font-size: 13.5px; box-shadow: 0 12px 30px -10px rgba(0, 0, 0, .5);
        transform: translate(-50%, 20px); opacity: 0; pointer-events: none; transition: opacity .2s, transform .2s;
    }
    .cart-toast.is-shown { opacity: 1; transform: translate(-50%, 0); pointer-events: auto; }
    .cart-toast a { color: #fb923c; font-weight: 600; text-decoration: none; white-space: nowrap; }
    .cart-toast span { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    @media (min-width: 768px) { .cart-toast { bottom: 28px; } }

    [data-cart-count] {
        position: absolute; top: 4px; right: 2px; min-width: 17px; height: 17px; padding: 0 4px; border-radius: 999px;
        background: #ef4444; color: #fff; font-size: 10px; font-weight: 700; line-height: 17px; text-align: center;
    }
    [data-cart-count][hidden] { display: none; }
    .cart-bump { animation: cart-bump .35s ease; }
    @keyframes cart-bump { 40% { transform: scale(1.25); } }

    /* Ikon keranjang di header desktop */
    .header-cart {
        position: relative; display: inline-grid; place-items: center; width: 40px; height: 40px; margin-right: 10px;
        border-radius: 999px; border: 1px solid rgba(255, 255, 255, .18); background: rgba(255, 255, 255, .06); color: #fff !important; vertical-align: middle;
    }
    .header-cart svg { width: 19px; height: 19px; }
    .header-cart [data-cart-count] { top: -4px; right: -4px; }
    html[data-theme='light'] .header-cart { color: #111418 !important; border-color: #e3e6ea; background: #f5f6f8; }

    /* Tombol tambah keranjang di detail produk (desktop) */
    .btn-add-cart {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 50px; padding: 0 22px; border-radius: 6px;
        border: 1px solid var(--primary-color, #e65200); background: transparent; color: var(--primary-color, #e65200); font-weight: 600; cursor: pointer;
    }
    .btn-add-cart:hover { background: rgba(230, 82, 0, .08); }
</style>

<div class="cart-toast" id="cart-toast" role="status" aria-live="polite">
    <span id="cart-toast-text">Ditambahkan ke keranjang</span>
    <a href="{{ route('cart', [], false) }}">Lihat</a>
</div>

<script>
    (function () {
        var KEY = 'xbt-cart';
        var read = function () { try { return JSON.parse(localStorage.getItem(KEY) || '[]') || []; } catch (e) { return []; } };
        var write = function (items) { try { localStorage.setItem(KEY, JSON.stringify(items)); } catch (e) {} render(); };
        var count = function () { return read().reduce(function (n, i) { return n + (i.qty || 0); }, 0); };

        var formatPrice = function (amount, currency) {
            return currency === 'IDR'
                ? 'Rp ' + Math.round(amount).toLocaleString('id-ID')
                : '$' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        var render = function () {
            var n = count();
            document.querySelectorAll('[data-cart-count]').forEach(function (el) {
                el.textContent = n > 99 ? '99+' : n;
                el.hidden = n === 0;
            });
            document.dispatchEvent(new CustomEvent('cart:changed'));
        };

        var toastTimer;
        var toast = function (text) {
            var el = document.getElementById('cart-toast');
            if (!el) return;
            document.getElementById('cart-toast-text').textContent = text;
            el.classList.add('is-shown');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function () { el.classList.remove('is-shown'); }, 2200);
        };

        window.XbtCart = {
            items: read,
            count: count,
            format: formatPrice,
            add: function (product, qty) {
                var items = read();
                var found = items.find(function (i) { return i.slug === product.slug; });
                if (found) { found.qty += qty || 1; } else { items.push(Object.assign({}, product, { qty: qty || 1 })); }
                write(items);
                toast('✓ Masuk keranjang: ' + product.name);
                document.querySelectorAll('[data-cart-count]').forEach(function (el) {
                    el.classList.remove('cart-bump'); void el.offsetWidth; el.classList.add('cart-bump');
                });
            },
            setQty: function (slug, qty) {
                var items = read().map(function (i) { return i.slug === slug ? Object.assign(i, { qty: Math.max(1, Math.min(99, qty)) }) : i; });
                write(items);
            },
            remove: function (slug) { write(read().filter(function (i) { return i.slug !== slug; })); },
            clear: function () { write([]); },
        };

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-cart-add]');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            try { window.XbtCart.add(JSON.parse(btn.getAttribute('data-cart-add'))); } catch (err) {}
        });

        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', render); } else { render(); }
        window.addEventListener('storage', function (e) { if (e.key === KEY) render(); });
    })();
</script>
