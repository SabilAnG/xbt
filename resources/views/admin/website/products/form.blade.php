@extends('layouts.admin', ['title' => $product->exists ? 'Ubah produk website' : 'Produk website baru', 'subtitle' => $product->exists ? $product->name : null])

@section('actions')
    <a href="{{ route('admin.web-products.index') }}" class="btn">Kembali</a>
    @if ($product->exists && $product->is_active)
        <a href="{{ route('products.show', $product->slug) }}" target="_blank" class="btn">Lihat di website ↗</a>
    @endif
@endsection

@section('content')
    <form method="POST" action="{{ $product->exists ? route('admin.web-products.update', $product) : route('admin.web-products.store') }}" enctype="multipart/form-data" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_440px] 2xl:grid-cols-[minmax(0,1fr)_520px]">
        <div class="min-w-0 max-w-4xl space-y-6">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        @if ($stockItem = $product->item)
            @php
                $hpp = (float) $stockItem->cost_price;
                $webPrice = (float) old('price', $product->price);
                $margin = $product->currency === 'IDR' ? $webPrice - $hpp : null;
            @endphp
            <section class="card overflow-hidden">
                <div class="card-header bg-brand-50">
                    <h2 class="card-title">Tertaut ke Stok Produk: <a href="{{ route('admin.items.show', $stockItem) }}" class="link">{{ $stockItem->sku }}</a></h2>
                    <x-admin.status :value="$stockItem->stockStatus()" />
                </div>
                <dl class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-5">
                    @foreach ([
                        'Harga modal (HPP)' => rupiah($hpp),
                        'Harga jual online' => rupiah($stockItem->sell_price_online),
                        'Harga jual offline' => rupiah($stockItem->sell_price),
                        'Sisa stok' => qty($stockItem->stock).' unit',
                        'Untung per unit (harga web − HPP)' => $margin === null ? '—' : rupiah($margin).($hpp > 0 ? ' ('.round($margin / $hpp * 100).'%)' : ''),
                    ] as $label => $value)
                        <div class="bg-white px-4 py-3">
                            <dt class="text-xs text-slate-500">{{ $label }}</dt>
                            <dd @class(['mt-1 text-sm font-semibold tabular-nums', 'text-rose-600' => $margin !== null && $margin < 0 && $loop->last])>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="px-5 py-3 text-xs text-slate-500">Harga modal dan harga jual hanya terlihat di admin. Website menampilkan <strong>Harga website</strong> di bawah.</p>
            </section>
        @endif

        <section class="card grid gap-4 p-6 sm:grid-cols-2">
            <x-admin.field name="name" label="Nama produk" :value="$product->name" required class="sm:col-span-2" />
            <x-admin.field name="slug" label="Slug URL" :value="$product->slug" hint="Kosongkan untuk dibuat otomatis dari nama." />
            <x-admin.field name="fitment" label="Cocok untuk (fitment)" :value="$product->fitment" />
            <x-admin.select name="item_id" label="Tautan ke Stok Produk" :value="$product->item_id" placeholder="— tidak ditautkan —"
                :options="$items->mapWithKeys(fn ($item) => [$item->id => $item->sku.' · '.$item->name.' (stok '.qty($item->stock).')'])"
                hint="Bila ditautkan, website menampilkan status stok dan admin melihat harga modal." />
            <div class="grid grid-cols-3 gap-3">
                <x-admin.field name="price" label="Harga website" type="number" step="any" min="0" :value="(float) $product->price" required class="col-span-2"
                    :hint="$product->item ? 'Harga jual online stok: '.rupiah($product->item->sell_price_online) : 'Harga yang tampil di halaman produk.'" />
                <x-admin.select name="currency" label="Mata uang" :options="['IDR' => 'Rp (IDR)', 'USD' => '$ (USD)']" :value="$product->currency ?? 'IDR'" :placeholder="false" required />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <x-admin.field name="sort_order" label="Urutan" type="number" min="0" :value="$product->sort_order" required />
                <div class="flex items-end pb-2">
                    <input type="hidden" name="is_active" value="{{ $product->is_active ? 1 : 0 }}">
                    @if ($product->is_active)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200"><span class="size-1.5 rounded-full bg-emerald-500"></span>Tayang di website</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200"><span class="size-1.5 rounded-full bg-amber-500"></span>Draf — belum tampil</span>
                    @endif
                </div>
            </div>
            <x-admin.field name="description" label="Deskripsi (HTML)" type="textarea" rows="10" :value="$product->description" class="sm:col-span-2"
                hint="Boleh memakai tag HTML seperti <p>, <ul><li>, <strong>." />
        </section>

        <section class="card" id="media-manager">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Foto &amp; video</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Urutan terkecil tampil pertama di galeri. Foto pertama dipakai untuk kartu produk &amp; keranjang.</p>
                </div>
                <span class="text-xs text-slate-500" data-media-count></span>
            </div>
            <div class="space-y-4 p-5">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" data-media-grid>
                    @foreach ($product->exists ? $product->images : [] as $media)
                        <div class="media-tile group relative overflow-hidden rounded-xl border border-slate-200 bg-white" data-media-existing
                            data-src="{{ asset($media->path) }}" data-thumb="{{ asset($media->thumb()) }}" data-type="{{ $media->isVideo() ? 'video' : 'image' }}">
                            <div class="relative aspect-square bg-slate-900">
                                @if ($media->isVideo())
                                    <video src="{{ asset($media->path) }}#t=0.5" muted playsinline preload="metadata" class="size-full object-cover"></video>
                                    <span class="absolute inset-0 grid place-items-center"><span class="grid size-10 place-items-center rounded-full bg-black/55 text-white"><svg class="ml-0.5 size-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span></span>
                                    <span class="absolute left-2 top-2 rounded-md bg-black/60 px-1.5 py-0.5 text-[10px] font-semibold text-white">VIDEO</span>
                                @else
                                    <img src="{{ asset($media->thumb()) }}" alt="" class="size-full object-cover">
                                @endif
                                <span class="media-main absolute right-2 top-2 hidden rounded-md bg-brand-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">UTAMA</span>
                            </div>
                            <div class="flex items-center gap-2 p-2">
                                <label class="flex items-center gap-1 text-[11px] text-slate-500">#
                                    <input type="number" min="0" name="images[{{ $media->id }}][sort_order]" value="{{ $media->sort_order }}" class="input w-14 px-2 py-1 text-xs" aria-label="Urutan" data-media-order>
                                </label>
                                <label class="ml-auto flex cursor-pointer items-center gap-1 rounded-md px-1.5 py-1 text-[11px] font-medium text-rose-600 hover:bg-rose-50">
                                    <input type="checkbox" name="images[{{ $media->id }}][delete]" value="1" class="rounded border-slate-300" data-media-delete> Hapus
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <label for="new_media" class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center transition hover:border-brand-400 hover:bg-brand-50/40" data-media-drop>
                    <span class="grid size-11 place-items-center rounded-full bg-white text-brand-600 shadow-sm ring-1 ring-slate-200">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                    </span>
                    <span class="text-sm font-medium text-slate-800">Seret foto / video ke sini, atau <span class="text-brand-600 underline">pilih berkas</span></span>
                    <span class="text-xs text-slate-500">Foto JPG/PNG/WebP maks 5 MB · Video MP4/WebM/MOV maks 50 MB · total sekali simpan maks 60 MB</span>
                    <input id="new_media" name="new_media[]" type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime" class="sr-only">
                </label>
                <p class="hidden rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700" data-media-warning></p>
                @error('new_media')<p class="error">{{ $message }}</p>@enderror
                @foreach ($errors->get('new_media.*') as $messages)
                    <p class="error">{{ $messages[0] }}</p>
                @endforeach
            </div>
        </section>

        <div class="flex items-center justify-between gap-2">
            <div>
                @if ($product->exists)
                    <button form="delete-product" class="btn btn-danger">Hapus produk</button>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.web-products.index') }}" class="btn">Batal</a>
                @if ($product->is_active)
                    <button name="publish" value="0" class="btn" title="Sembunyikan dari website">Tarik jadi draf</button>
                    <button name="publish" value="1" class="btn btn-primary">Simpan perubahan</button>
                @else
                    <button name="publish" value="0" class="btn">Simpan draf</button>
                    <button name="publish" value="1" class="btn btn-primary">Posting ke website</button>
                @endif
            </div>
        </div>
        </div>

        <aside class="preview-panel xl:sticky xl:top-4 xl:self-start" data-preview-panel aria-label="Pratinjau halaman website">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <div class="mr-auto">
                    <h2 class="text-sm font-semibold text-slate-900">Pratinjau halaman produk</h2>
                    <p class="text-[11px] text-slate-500" data-preview-status>Tampilan asli website, ikut berubah saat Anda mengetik.</p>
                </div>
                <div class="inline-flex rounded-lg bg-slate-100 p-0.5 text-xs font-medium" role="group" aria-label="Ukuran layar">
                    <button type="button" class="rounded-md px-2.5 py-1 text-slate-600" data-preview-device="mobile">HP</button>
                    <button type="button" class="rounded-md px-2.5 py-1 text-slate-600" data-preview-device="desktop">Desktop</button>
                </div>
                <button type="button" class="btn px-2.5 py-1 text-xs" data-preview-expand>Perbesar</button>
            </div>
            <div class="preview-frame relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-100" data-preview-frame>
                <iframe name="product-preview" title="Pratinjau halaman produk" class="absolute left-0 top-0 origin-top-left border-0 bg-white" data-preview-iframe></iframe>
                <div class="pointer-events-none absolute right-3 top-3 hidden rounded-full bg-slate-900/80 px-2.5 py-1 text-[11px] font-medium text-white" data-preview-loading>Memperbarui…</div>
            </div>
            <p class="mt-2 text-[11px] text-slate-500">Pratinjau belum menyimpan apa pun. Klik <strong>Posting ke website</strong> bila sudah oke.</p>
        </aside>
    </form>

    <form method="POST" action="{{ route('admin.web-products.preview') }}" target="product-preview" class="hidden" data-preview-form>
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <div data-preview-fields></div>
    </form>

    @if ($product->exists)
        <form id="delete-product" method="POST" action="{{ route('admin.web-products.destroy', $product) }}" onsubmit="return confirm('Hapus produk ini beserta fotonya?')">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection

@push('scripts')
<style>
    .preview-panel.is-expanded { position: fixed; inset: 0; z-index: 60; margin: 0; padding: 16px 20px; background: rgb(248 250 252); }
</style>
<script>
    (function () {
        var root = document.getElementById('media-manager');
        if (!root) return;

        var form = root.closest('form');
        var grid = root.querySelector('[data-media-grid]');
        var input = document.getElementById('new_media');
        var drop = root.querySelector('[data-media-drop]');
        var warning = root.querySelector('[data-media-warning]');
        var counter = root.querySelector('[data-media-count]');
        var LIMIT = { image: 5, video: 50 };
        var TOTAL_LIMIT = 60;
        var pending = []; // { file, url, type }

        function typeOf(file) { return file.type.indexOf('video/') === 0 ? 'video' : 'image'; }

        function syncInput() {
            var dt = new DataTransfer();
            pending.forEach(function (p) { dt.items.add(p.file); });
            input.files = dt.files;
        }

        function addFiles(list) {
            var problems = [];
            Array.prototype.forEach.call(list, function (file) {
                if (!/^(image\/(jpeg|png|webp|gif)|video\/(mp4|webm|quicktime))$/.test(file.type)) {
                    problems.push('"' + file.name + '" bukan foto/video yang didukung.');
                    return;
                }
                var type = typeOf(file);
                if (file.size > LIMIT[type] * 1024 * 1024) {
                    problems.push('"' + file.name + '" melebihi ' + LIMIT[type] + ' MB.');
                    return;
                }
                pending.push({ file: file, url: URL.createObjectURL(file), type: type });
            });
            var total = pending.reduce(function (sum, p) { return sum + p.file.size; }, 0) / 1024 / 1024;
            if (total > TOTAL_LIMIT) problems.push('Total berkas baru ' + total.toFixed(1) + ' MB — simpan bertahap, maks ' + TOTAL_LIMIT + ' MB sekali simpan.');
            warning.textContent = problems.join(' ');
            warning.classList.toggle('hidden', problems.length === 0);
            syncInput();
            render();
            schedule();
        }

        function tileFor(p, index) {
            var el = document.createElement('div');
            el.className = 'media-tile relative overflow-hidden rounded-xl border-2 border-dashed border-brand-300 bg-white';
            el.dataset.mediaPending = index;
            el.innerHTML =
                '<div class="relative aspect-square bg-slate-900">' +
                (p.type === 'video'
                    ? '<video src="' + p.url + '#t=0.5" muted playsinline preload="metadata" class="size-full object-cover"></video>' +
                      '<span class="absolute left-2 top-2 rounded-md bg-black/60 px-1.5 py-0.5 text-[10px] font-semibold text-white">VIDEO</span>'
                    : '<img src="' + p.url + '" alt="" class="size-full object-cover">') +
                '<span class="media-main absolute right-2 top-2 hidden rounded-md bg-brand-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">UTAMA</span>' +
                '</div>' +
                '<div class="flex items-center gap-2 p-2">' +
                '<span class="rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold text-brand-700">BARU</span>' +
                '<span class="truncate text-[11px] text-slate-500">' + (p.file.size / 1024 / 1024).toFixed(1) + ' MB</span>' +
                '<button type="button" class="ml-auto rounded-md px-1.5 py-1 text-[11px] font-medium text-rose-600 hover:bg-rose-50" data-remove>Batal</button>' +
                '</div>';
            el.querySelector('[data-remove]').addEventListener('click', function () {
                URL.revokeObjectURL(p.url);
                pending.splice(pending.indexOf(p), 1);
                syncInput();
                render();
                schedule();
            });
            return el;
        }

        /** Urutan galeri seperti di website: media lama menurut nomor urut, lalu media baru. */
        function gallery() {
            var existing = Array.prototype.slice.call(grid.querySelectorAll('[data-media-existing]'))
                .filter(function (el) {
                    var del = el.querySelector('[data-media-delete]').checked;
                    el.classList.toggle('opacity-40', del);
                    return !del;
                })
                .map(function (el) { return { el: el, src: el.dataset.src, thumb: el.dataset.thumb, type: el.dataset.type, order: +el.querySelector('[data-media-order]').value || 0 }; })
                .sort(function (a, b) { return a.order - b.order; });
            var fresh = Array.prototype.slice.call(grid.querySelectorAll('[data-media-pending]'))
                .map(function (el) { var p = pending[+el.dataset.mediaPending]; return { el: el, src: p.url, thumb: p.url, type: p.type }; });
            return existing.concat(fresh);
        }

        function render() {
            grid.querySelectorAll('[data-media-pending]').forEach(function (el) { el.remove(); });
            pending.forEach(function (p, i) { grid.appendChild(tileFor(p, i)); });

            var items = gallery();
            var firstPhoto = items.filter(function (m) { return m.type === 'image'; })[0];
            grid.querySelectorAll('.media-main').forEach(function (b) { b.classList.add('hidden'); });
            if (firstPhoto) firstPhoto.el.querySelector('.media-main').classList.remove('hidden');
            var photos = items.filter(function (m) { return m.type === 'image'; }).length;
            counter.textContent = photos + ' foto · ' + (items.length - photos) + ' video';
        }

        // ---- Pratinjau halaman website asli (iframe)
        var panel = document.querySelector('[data-preview-panel]');
        var frameBox = panel.querySelector('[data-preview-frame]');
        var iframe = panel.querySelector('[data-preview-iframe]');
        var loading = panel.querySelector('[data-preview-loading]');
        var pvForm = document.querySelector('[data-preview-form]');
        var pvFields = pvForm.querySelector('[data-preview-fields]');
        var device = 'mobile';
        var expanded = false;
        var timer = null;
        var keepScroll = 0;
        try { device = localStorage.getItem('xbt.preview.device') || 'mobile'; } catch (e) {}

        function layout() {
            var width = device === 'desktop' ? 1280 : 390;
            var boxWidth = frameBox.clientWidth;
            var boxHeight = expanded ? window.innerHeight - 90 : Math.max(window.innerHeight - 130, 520);
            var scale = Math.min(1, boxWidth / width);
            frameBox.style.height = boxHeight + 'px';
            iframe.style.width = width + 'px';
            iframe.style.height = (boxHeight / scale) + 'px';
            iframe.style.transform = 'scale(' + scale + ')';
            iframe.style.left = Math.max(0, (boxWidth - width * scale) / 2) + 'px';
            panel.querySelectorAll('[data-preview-device]').forEach(function (b) {
                var on = b.dataset.previewDevice === device;
                b.classList.toggle('bg-white', on); b.classList.toggle('shadow-sm', on); b.classList.toggle('text-slate-900', on);
            });
        }

        function field(name, value) {
            var el = document.createElement('input');
            el.type = 'hidden'; el.name = name; el.value = value;
            pvFields.appendChild(el);
        }

        function refresh() {
            clearTimeout(timer);
            try { keepScroll = iframe.contentWindow ? iframe.contentWindow.scrollY : 0; } catch (e) { keepScroll = 0; }
            pvFields.innerHTML = '';
            ['name', 'slug', 'fitment', 'price', 'currency', 'item_id', 'description'].forEach(function (n) {
                var el = form.querySelector('[name=' + n + ']');
                if (el) field(n, el.value);
            });
            form.querySelectorAll('[data-media-order]').forEach(function (el) { field(el.name, el.value); });
            form.querySelectorAll('[data-media-delete]:checked').forEach(function (el) { field(el.name, '1'); });
            pending.forEach(function (p) { field('preview_new[]', p.type); });
            loading.classList.remove('hidden');
            pvForm.submit();
        }

        function schedule() { clearTimeout(timer); timer = setTimeout(refresh, 600); }

        iframe.addEventListener('load', function () {
            loading.classList.add('hidden');
            var doc;
            try { doc = iframe.contentDocument; } catch (e) { return; }
            if (!doc) return;
            // Foto/video yang belum diunggah: ganti penanda dengan berkas lokal.
            doc.querySelectorAll('img, video, source').forEach(function (el) {
                var src = el.getAttribute('src') || '';
                var m = src.match(/__pending__\/(\d+)(#.*)?$/);
                if (!m || !pending[+m[1]]) return;
                el.setAttribute('src', pending[+m[1]].url + (m[2] || ''));
                if (el.tagName === 'SOURCE') el.parentNode.load();
            });
            // Tautan di pratinjau dibuka di tab baru supaya pratinjau tidak berpindah halaman.
            doc.querySelectorAll('a[href]').forEach(function (a) { if (!a.getAttribute('href').startsWith('#') && !a.getAttribute('onclick')) a.target = '_blank'; });
            iframe.contentWindow.scrollTo(0, keepScroll);
        });

        panel.querySelectorAll('[data-preview-device]').forEach(function (b) {
            b.addEventListener('click', function () {
                device = b.dataset.previewDevice;
                try { localStorage.setItem('xbt.preview.device', device); } catch (e) {}
                keepScroll = 0;
                layout(); refresh();
            });
        });
        panel.querySelector('[data-preview-expand]').addEventListener('click', function () {
            expanded = !expanded;
            panel.classList.toggle('is-expanded', expanded);
            this.textContent = expanded ? 'Tutup' : 'Perbesar';
            document.body.style.overflow = expanded ? 'hidden' : '';
            layout();
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && expanded) panel.querySelector('[data-preview-expand]').click(); });
        window.addEventListener('resize', layout);

        form.addEventListener('input', function (e) { if (e.target.type !== 'file') schedule(); });
        form.addEventListener('change', function (e) { if (e.target.type !== 'file') schedule(); });

        input.addEventListener('change', function () {
            var files = Array.prototype.slice.call(input.files);
            // input.files sudah diganti pilihan baru; gabungkan dengan antrean sebelumnya.
            addFiles(files);
        });
        ['dragenter', 'dragover'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('border-brand-500', 'bg-brand-50'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('border-brand-500', 'bg-brand-50'); });
        });
        drop.addEventListener('drop', function (e) { addFiles(e.dataTransfer.files); });
        grid.addEventListener('input', render);
        grid.addEventListener('change', render);
        form.addEventListener('submit', function (e) {
            var total = pending.reduce(function (sum, p) { return sum + p.file.size; }, 0) / 1024 / 1024;
            if (total > TOTAL_LIMIT) { e.preventDefault(); warning.classList.remove('hidden'); warning.scrollIntoView({ block: 'center' }); }
        });

        render();
        layout();
        refresh();
    })();
</script>
@endpush
