{{-- Daftar produk versi HP: judul ringkas, urutan, chip merek, grid 2 kolom dengan tombol keranjang. --}}
@php
    $haystack = strtolower($products->map(fn ($p) => $p->name.' '.$p->fitment)->implode(' '));
    $brands = collect(['Harley Davidson', 'Yamaha', 'Honda', 'Kawasaki', 'Suzuki', 'BMW', 'Ducati', 'KTM', 'Vespa', 'Mio', 'Nmax', 'Aerox', 'Vario', 'PCX', 'Beat', 'Scoopy'])
        ->filter(fn ($brand) => str_contains($haystack, strtolower($brand)))->values();
@endphp

<style>
    .m-plp { display: none; }
    @media (max-width: 767px) {
        .m-plp { display: block; padding: 14px 14px 8px; }
        .m-plp-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
        .m-plp-head h1 { font-size: 20px !important; line-height: 1.2 !important; margin: 0 !important; text-transform: none; letter-spacing: 0; color: #fff; }
        .m-plp-head small { display: block; margin-top: 2px; font-size: 12px; color: rgba(255, 255, 255, .5); }
        .m-plp-sort { position: relative; flex: 0 0 auto; }
        .m-plp-sort select {
            height: 34px; padding: 0 30px 0 12px; border-radius: 999px; font-size: 12.5px; font-weight: 500;
            background: rgba(255, 255, 255, .06); color: #fff; border: 1px solid rgba(255, 255, 255, .1);
            -webkit-appearance: none; appearance: none; outline: none;
        }
        .m-plp-sort::after { content: ''; position: absolute; right: 13px; top: 12px; width: 6px; height: 6px; border-right: 2px solid currentColor; border-bottom: 2px solid currentColor; transform: rotate(45deg); opacity: .6; pointer-events: none; color: #fff; }
        .m-plp-chips { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: 0 -14px 14px; padding: 0 14px; }
        .m-plp-chips::-webkit-scrollbar { display: none; }
        .m-plp-chips button {
            flex: 0 0 auto; height: 32px; padding: 0 14px; border-radius: 999px; font-size: 12.5px; white-space: nowrap;
            background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .1); color: rgba(255, 255, 255, .85);
        }
        .m-plp-chips button.is-active { background: var(--primary-color, #f97316); border-color: var(--primary-color, #f97316); color: #fff; font-weight: 600; }
        .m-plp-empty { text-align: center; padding: 40px 10px; color: rgba(255, 255, 255, .55); font-size: 14px; }

        html[data-theme='light'] .m-plp-head h1 { color: #111418; }
        html[data-theme='light'] .m-plp-head small { color: #6b7280; }
        html[data-theme='light'] .m-plp-sort select, html[data-theme='light'] .m-plp-chips button:not(.is-active) { background: #f3f4f6; border-color: #e5e7eb; color: #111418; }
        html[data-theme='light'] .m-plp-sort::after { color: #111418; }
        html[data-theme='light'] .m-plp-empty { color: #6b7280; }
    }
</style>

<div class="m-plp">
    <div class="m-plp-head">
        <div>
            <h1>Semua Produk</h1>
            <small><span id="m-plp-count">{{ $products->count() }}</span> produk</small>
        </div>
        <label class="m-plp-sort">
            <span class="visually-hidden">Urutkan</span>
            <select id="m-plp-sort">
                <option value="default">Rekomendasi</option>
                <option value="new">Terbaru</option>
                <option value="cheap">Harga terendah</option>
                <option value="expensive">Harga tertinggi</option>
            </select>
        </label>
    </div>

    @if ($brands->isNotEmpty())
        <div class="m-plp-chips" id="m-plp-chips">
            <button type="button" class="is-active" data-brand="">Semua</button>
            @foreach ($brands as $brand)
                <button type="button" data-brand="{{ strtolower($brand) }}">{{ $brand }}</button>
            @endforeach
        </div>
    @endif

    <div class="m-grid" id="m-plp-grid">
        @foreach ($products as $product)
            @include('partials.m-product-card', ['product' => $product])
        @endforeach
    </div>
    <p class="m-plp-empty" id="m-plp-empty" hidden>Produk tidak ditemukan. Coba kata kunci lain.</p>
</div>

<script>
    (function () {
        var grid = document.getElementById('m-plp-grid');
        if (!grid) return;
        var cards = Array.prototype.slice.call(grid.children);
        var brand = '';
        var search = function () { var el = document.getElementById('m-product-search'); return el ? el.value.trim().toLowerCase() : ''; };

        var apply = function () {
            var q = search();
            var shown = 0;
            cards.forEach(function (card) {
                var name = card.dataset.name;
                var match = (!q || name.indexOf(q) !== -1) && (!brand || name.indexOf(brand) !== -1);
                card.hidden = !match;
                if (match) shown++;
            });
            document.getElementById('m-plp-count').textContent = shown;
            document.getElementById('m-plp-empty').hidden = shown > 0;
        };

        document.getElementById('m-plp-sort').addEventListener('change', function () {
            var mode = this.value;
            var sorted = cards.slice().sort(function (a, b) {
                if (mode === 'cheap') return a.dataset.price - b.dataset.price;
                if (mode === 'expensive') return b.dataset.price - a.dataset.price;
                if (mode === 'new') return b.dataset.id - a.dataset.id;
                return a.dataset.order - b.dataset.order || a.dataset.id - b.dataset.id;
            });
            sorted.forEach(function (card) { grid.appendChild(card); });
        });

        var chips = document.getElementById('m-plp-chips');
        if (chips) {
            chips.addEventListener('click', function (e) {
                var btn = e.target.closest('button');
                if (!btn) return;
                chips.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
                brand = btn.dataset.brand;
                apply();
            });
        }

        var input = document.getElementById('m-product-search');
        if (input) input.addEventListener('input', apply);
        apply();
    })();
</script>
