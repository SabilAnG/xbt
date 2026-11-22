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
        .m-plp-bar { display: flex; align-items: center; gap: 8px; margin: 0 -14px 12px; padding: 0 14px; }
        .m-plp-chips { display: flex; gap: 6px; flex: 1; min-width: 0; overflow-x: auto; scrollbar-width: none; -webkit-mask-image: linear-gradient(90deg, #000 88%, transparent); mask-image: linear-gradient(90deg, #000 88%, transparent); }
        .m-plp-chips::-webkit-scrollbar { display: none; }
        .m-plp-chips button {
            flex: 0 0 auto; height: 30px; padding: 0 12px; border-radius: 999px; font-size: 12.5px; white-space: nowrap;
            background: transparent; border: 1px solid rgba(255, 255, 255, .14); color: rgba(255, 255, 255, .8);
        }
        .m-plp-chips button span { margin-left: 2px; opacity: .75; font-weight: 500; }
        .m-plp-chips button.is-active { background: var(--primary-color, #f97316); border-color: var(--primary-color, #f97316); color: #fff; font-weight: 600; }
        .m-plp-sort { position: relative; flex: 0 0 auto; display: grid; place-items: center; width: 34px; height: 30px; border-radius: 999px; border: 1px solid rgba(255, 255, 255, .14); color: #fff; }
        .m-plp-sort svg { width: 16px; height: 16px; pointer-events: none; }
        .m-plp-sort select { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .m-plp-sort.is-set { border-color: var(--primary-color, #f97316); color: var(--primary-color, #f97316); }
        .m-plp-empty { text-align: center; padding: 40px 10px; color: rgba(255, 255, 255, .55); font-size: 14px; }

        html[data-theme='light'] .m-plp-chips button:not(.is-active), html[data-theme='light'] .m-plp-sort:not(.is-set) { border-color: #e5e7eb; color: #374151; }
        html[data-theme='light'] .m-plp-empty { color: #6b7280; }
    }
</style>

<div class="m-plp">
    <h1 class="visually-hidden">Semua Produk</h1>
    <div class="m-plp-bar">
        <div class="m-plp-chips" id="m-plp-chips">
            <button type="button" class="is-active" data-brand="">Semua <span>{{ $products->count() }}</span></button>
            @foreach ($brands as $brand)
                <button type="button" data-brand="{{ strtolower($brand) }}">{{ $brand }}</button>
            @endforeach
        </div>
        <label class="m-plp-sort" aria-label="Urutkan">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 4v16m0 0-3-3m3 3 3-3M17 20V4m0 0-3 3m3-3 3 3"/></svg>
            <select id="m-plp-sort">
                <option value="default">Rekomendasi</option>
                <option value="new">Terbaru</option>
                <option value="cheap">Harga terendah</option>
                <option value="expensive">Harga tertinggi</option>
            </select>
        </label>
    </div>

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
            document.getElementById('m-plp-empty').hidden = shown > 0;
        };

        document.getElementById('m-plp-sort').addEventListener('change', function () {
            var mode = this.value;
            this.parentNode.classList.toggle('is-set', mode !== 'default');
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
