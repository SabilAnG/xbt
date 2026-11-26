@extends('layouts.admin', ['title' => $formula->exists ? 'Ubah formula' : 'Formula baru'])

@section('content')
    <form method="POST" action="{{ $formula->exists ? route('admin.formulas.update', $formula) : route('admin.formulas.store') }}" class="space-y-6" data-formula-form>
        @csrf
        @if ($formula->exists) @method('PUT') @endif

        @if ($errors->any())
            <div class="card border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                <p class="font-semibold">Formula belum bisa disimpan:</p>
                <ul class="mt-1 list-disc pl-5">@foreach (collect($errors->all())->unique() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="min-w-0 space-y-6">
                <section class="card space-y-4 p-6">
                    <h2 class="card-title">Yang dibuat</h2>
                    @php $semi = old('output_type', $formula->output_type) === \App\Models\Formula::SEMI_FINISHED; @endphp
                    <div class="grid gap-4 sm:grid-cols-6">
                        <x-admin.select name="output_type" label="Hasil formula" :options="\App\Models\Formula::OUTPUTS" :value="$formula->output_type" :placeholder="false" required class="sm:col-span-6" data-output />
                        <x-admin.option-select name="motor_category" label="Kategori motor" kind="kategori" :options="$motorOptions['kategori']" :value="$formula->motorCategoryLabel()" required class="sm:col-span-3" />
                        <x-admin.option-select name="motor_brand" label="Brand motor" kind="brand" :options="$motorOptions['brand']" :value="$formula->motor_brand" placeholder="— pilih brand —" class="sm:col-span-3" />
                        <x-admin.option-select name="motor_type" label="Type motor" kind="type" :options="$motorOptions['type']" :parents="$typeBrands" parent-field="motor_brand" :value="$formula->motor_type" placeholder="— pilih type —" :required="! $semi" class="sm:col-span-3" data-name-source />
                        <x-admin.option-select name="variant" label="Varian / model knalpot" kind="varian" :options="$motorOptions['varian']" :value="$formula->variant" placeholder="— pilih varian —" class="sm:col-span-3" data-name-source />
                        <x-admin.field name="name" label="Nama formula" :value="$formula->name" required class="sm:col-span-4" placeholder="Terisi dari type + varian" data-name />
                        <x-admin.field name="code" label="Kode" :value="$formula->code" placeholder="Kosongkan = otomatis" class="sm:col-span-2" />
                        <div data-knalpot-only @class(['sm:col-span-4', 'hidden' => $semi])>
                            <x-admin.select name="item_id" label="Produk jadi (Finish Good)" :options="$items" :value="$formula->item_id" placeholder="— buat otomatis dari formula —"
                                hint="Hasil produksi formula ini masuk ke stok produk ini." />
                        </div>
                        @php $vendorWork = old('work_mode', $formula->work_mode) === 'vendor'; @endphp
                        <div data-semi-only @class(['sm:col-span-6 rounded-[14px] border border-white bg-white/70 p-4 shadow-(--shadow-soft)', 'hidden' => ! $semi]) data-work-box>
                            <p class="label">Dikerjakan oleh</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach (['internal' => 'Bengkel sendiri', 'vendor' => 'Vendor (bending, chrome, cat, …)'] as $mode => $modeLabel)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="work_mode" value="{{ $mode }}" class="peer sr-only" data-work-mode @checked(($vendorWork ? 'vendor' : 'internal') === $mode)>
                                        <span class="inline-flex h-9 items-center rounded-[10px] border border-white bg-white px-3.5 text-[12.5px] font-medium text-ink-soft shadow-(--shadow-soft) transition peer-checked:border-transparent peer-checked:bg-linear-to-b peer-checked:from-[#f9922a] peer-checked:to-[#f2621f] peer-checked:text-white hover:text-ink">{{ $modeLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div @class(['mt-4', 'hidden' => ! $vendorWork]) data-vendor-fields>
                                <label for="vendor_service_id" class="label">Jasa vendor<span class="text-red-500"> *</span></label>
                                <div class="flex items-center gap-2">
                                    <div class="min-w-0 flex-1">
                                        <select id="vendor_service_id" name="vendor_service_id" class="input" data-semi-service>
                                            <option value="">— pilih jasa vendor —</option>
                                            @foreach ($vendorServices as $service)
                                                <option value="{{ $service['id'] }}" @selected((string) old('vendor_service_id', $formula->vendor_service_id) === (string) $service['id'])>{{ $service['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="button" class="tile size-10 text-brand-600" data-service-add data-service-target="formula" title="Tambah jasa vendor baru" aria-label="Tambah jasa vendor baru">
                                        <svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                                    </button>
                                </div>
                                @error('vendor_service_id')<p class="error">{{ $message }}</p>@enderror
                                <p class="hint">Tarifnya ikut dihitung ke HPP. Kelola di <a href="{{ route('admin.vendor-services.index') }}" class="link" target="_blank">Jasa Vendor</a>.</p>
                            </div>
                        </div>
                        <div data-semi-only @class(['sm:col-span-4', 'hidden' => ! $semi])>
                            <x-admin.select name="output_material_id" label="Barang setengah jadi yang dihasilkan" :options="$semiMaterials" :value="$formula->output_material_id" placeholder="— buat otomatis dari formula —"
                                hint="Masuk Gudang Setengah Jadi; bisa dikerjakan sendiri atau lewat vendor." />
                        </div>
                        <div class="flex items-end pb-2 sm:col-span-2">
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $formula->is_active ?? true))> Aktif
                            </label>
                        </div>
                    </div>
                </section>

                <div class="space-y-4" data-parts></div>
                <button type="button" class="btn w-full border-dashed" data-add-part>+ Tambah bagian</button>

                <section class="card space-y-3 p-6">
                    <div>
                        <h2 class="card-title">Ongkos per knalpot</h2>
                        <p class="text-xs text-slate-500">Biaya di luar bahan per knalpot. Pilih dari <a href="{{ route('admin.cost-options.index') }}" class="link" target="_blank">Data Ongkos</a>, tarifnya terisi otomatis.</p>
                        <datalist id="cost-names">@foreach ($costOptions as $option)<option value="{{ $option['name'] }}">@endforeach</datalist>
                    </div>
                    <div class="space-y-2" data-costs></div>
                    <button type="button" class="btn btn-sm" data-add-cost>+ Ongkos</button>
                </section>

                <x-admin.field name="notes" label="Catatan" type="textarea" :value="$formula->notes" />
            </div>

            <aside class="space-y-4 xl:sticky xl:top-20 xl:self-start">
                <section class="card p-5">
                    <h2 class="card-title">HPP perkiraan / knalpot</h2>
                    <dl class="mt-3 space-y-1.5 text-sm" data-summary></dl>
                    <p class="mt-3 text-xs text-slate-500">Harga bahan = harga rata-rata stok (pipa per cm). Saat produksi, biaya sebenarnya dihitung dari batang/potongan yang dipakai, termasuk serbuk potong dan sisa yang diafkir.</p>
                </section>
                <div class="flex gap-2">
                    <a href="{{ $formula->exists ? route('admin.formulas.show', $formula) : route('admin.formulas.index') }}" class="btn flex-1">Batal</a>
                    <button class="btn btn-primary flex-1">Simpan formula</button>
                </div>
                <p class="text-xs text-slate-500">Bahan belum ada? Tambahkan di <a href="{{ route('admin.materials.create') }}" class="link" target="_blank">Master Bahan</a> lalu muat ulang halaman ini.</p>
            </aside>
        </div>
    </form>

    @if ($formula->exists)
        <div class="border-t border-slate-200 pt-4">
            <x-admin.delete-button :action="route('admin.formulas.destroy', $formula)" label="Hapus formula" />
        </div>
    @endif
    <div class="absolute z-50 hidden w-[340px] rounded-2xl border border-white bg-white p-4 shadow-(--shadow-pop)" data-service-pop>
        <p class="text-[13px] font-semibold text-ink">Tambah jasa vendor</p>
        <div class="mt-3 space-y-2.5">
            <input class="input h-9 text-[13px]" list="formula-vendors" placeholder="Vendor, mis. Bengkel Bending Jaya" data-pop-vendor aria-label="Vendor">
            <input class="input h-9 text-[13px]" list="formula-processes" placeholder="Proses, mis. Bending" data-pop-process aria-label="Proses">
            <div class="flex gap-2">
                <input type="number" step="any" min="0" class="input h-9 flex-1 text-[13px]" placeholder="Tarif (Rp)" data-pop-rate aria-label="Tarif">
                <input class="input h-9 w-24 text-[13px]" value="buah" data-pop-unit aria-label="Satuan">
            </div>
        </div>
        <datalist id="formula-vendors">@foreach ($vendors as $vendorName)<option value="{{ $vendorName }}">@endforeach</datalist>
        <datalist id="formula-processes">@foreach ($processes->merge(['Bending', 'Chrome', 'Cat / powder coating', 'Las', 'Poles', 'Bubut'])->unique() as $process)<option value="{{ $process }}">@endforeach</datalist>
        <p class="mt-2 hidden text-[11.5px] text-rose-600" data-error></p>
        <div class="mt-3 flex justify-end gap-2">
            <button type="button" class="btn btn-sm" data-pop-cancel>Batal</button>
            <button type="button" class="btn btn-primary btn-sm" data-pop-save>Simpan jasa</button>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.querySelector('[data-formula-form]');
    var state = { parts: @json($parts), costs: @json($costs) };
    var materials = @json($materials);
    var warehouses = @json($warehouses);
    var suggestions = @json($suggestions);
    if (Array.isArray(suggestions)) suggestions = {};
    var presetUrl = @json(route('admin.component-options.store'));
    var services = @json($vendorServices);
    var costOptions = @json($costOptions);
    var servicesById = {};
    services.forEach(function (s) { servicesById[s.id] = s; });
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    (function () {
        var list = document.createElement('datalist');
        list.id = 'part-names';
        @json($partNames).forEach(function (n) { var o = document.createElement('option'); o.value = n; list.appendChild(o); });
        document.body.appendChild(list);
    })();
    var byId = {};
    materials.forEach(function (m) { byId[m.id] = m; });

    var partsBox = form.querySelector('[data-parts]');
    var costsBox = form.querySelector('[data-costs]');
    var summary = form.querySelector('[data-summary]');

    function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
    function num(v) { var n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? 0 : n; }
    function rp(v) { return 'Rp ' + Math.round(v).toLocaleString('id-ID'); }
    function fmt(v, d) { return Number(v).toLocaleString('id-ID', { maximumFractionDigits: d == null ? 2 : d }); }

    function lineCost(line, compQty) {
        var m = byId[line.material_id];
        return m ? num(line.qty) * m.cost * num(compQty || 1) : 0;
    }

    function materialOptions(warehouse, selected) {
        var groups = {};
        materials.filter(function (m) { return m.warehouse === warehouse; }).forEach(function (m) {
            (groups[m.category] = groups[m.category] || []).push(m);
        });
        var html = '<option value="">— pilih barang —</option>';
        Object.keys(groups).sort().forEach(function (g) {
            html += '<optgroup label="' + esc(g) + '">';
            groups[g].forEach(function (m) {
                html += '<option value="' + m.id + '"' + (String(selected) === String(m.id) ? ' selected' : '') + '>' + esc(m.label) + ' — ' + rp(m.cost) + '/' + esc(m.unit) + '</option>';
            });
            html += '</optgroup>';
        });
        return html;
    }

    function serviceOptions(selected) {
        return '<option value="">— pilih jasa vendor —</option>' + services.map(function (s) {
            return '<option value="' + s.id + '"' + (String(selected) === String(s.id) ? ' selected' : '') + '>' + esc(s.process + ' · ' + s.vendor + ' — ' + rp(s.rate) + '/' + s.unit) + '</option>';
        }).join('');
    }

    function serviceInfo(comp) {
        var s = servicesById[comp.vendor_service_id];
        return s ? rp(s.rate) + ' × ' + fmt(num(comp.qty || 1)) + ' = <strong class="text-ink">' + rp(s.rate * num(comp.qty || 1)) + '</strong> / knalpot' : 'Belum ada jasa? Klik + untuk menambah.';
    }

    function lineHtml(p, c, l, line, compQty) {
        var m = byId[line.material_id];
        var wh = line.warehouse || (m ? m.warehouse : 'bahan_baku');
        var base = 'parts[' + p + '][components][' + c + '][materials][' + l + ']';
        var whOptions = Object.keys(warehouses).map(function (k) { return '<option value="' + k + '"' + (k === wh ? ' selected' : '') + '>' + esc(warehouses[k]) + '</option>'; }).join('');
        var hint = '';
        if (m && m.pipe) { hint = 'Panjang potong per komponen · batang ' + fmt(m.bar) + ' cm · stok ' + fmt(m.stock) + ' cm'; }
        else if (m) { hint = 'Stok ' + fmt(m.stock, 3) + ' ' + esc(m.unit); }
        return '<div class="grid gap-2 rounded-lg bg-slate-50 p-2 sm:grid-cols-[13rem_minmax(0,1fr)_8.5rem_7rem_auto] sm:items-start" data-line data-p="' + p + '" data-c="' + c + '" data-l="' + l + '">'
            + '<select class="input" data-field="warehouse" aria-label="Gudang">' + whOptions + '</select>'
            + '<div><select name="' + base + '[material_id]" class="input" data-field="material_id" aria-label="Barang">' + materialOptions(wh, line.material_id) + '</select>'
            + (hint ? '<p class="mt-1 text-[11px] text-slate-500">' + hint + '</p>' : '') + '</div>'
            + '<div class="flex items-center gap-1"><input name="' + base + '[qty]" type="number" step="any" min="0" value="' + esc(line.qty) + '" class="input" data-field="qty" placeholder="' + (m && m.pipe ? 'cm' : 'Jumlah') + '" aria-label="Kebutuhan">'
            + '<span class="w-9 shrink-0 text-xs text-slate-500">' + (m ? esc(m.unit) : '') + '</span></div>'
            + '<p class="py-2 text-right text-sm tabular-nums text-slate-700" data-line-cost>' + rp(lineCost(line, compQty)) + '</p>'
            + '<button type="button" class="btn btn-ghost px-2 text-rose-600" data-remove-line aria-label="Hapus bahan">✕</button>'
            + '</div>';
    }

    function componentHtml(p, c, comp, partName) {
        var base = 'parts[' + p + '][components][' + c + ']';
        var list = 'sug-' + p;
        var lines = (comp.materials || []).map(function (line, l) { return lineHtml(p, c, l, line, comp.qty); }).join('');
        var vendor = comp.work_mode === 'vendor';
        return '<div class="rounded-xl border border-slate-200 p-3" data-component data-p="' + p + '" data-c="' + c + '">'
            + '<div class="flex flex-wrap items-center gap-2">'
            + '<input name="' + base + '[name]" value="' + esc(comp.name) + '" class="input max-w-56 font-medium" list="' + list + '" placeholder="Nama komponen, mis. P1" data-field="name" aria-label="Nama komponen">'
            + '<button type="button" class="tile size-10 text-brand-600" data-preset-add title="Simpan nama komponen ke Data Komponen" aria-label="Simpan ke Data Komponen">'
            + '<svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>'
            + '<span class="text-xs text-slate-500">×</span>'
            + '<input name="' + base + '[qty]" type="number" step="any" min="0" value="' + esc(comp.qty) + '" class="input w-20" data-field="cqty" aria-label="Jumlah per knalpot">'
            + '<span class="text-xs text-slate-500">buah / knalpot</span>'
            + '<button type="button" class="' + (vendor ? 'btn btn-sm border-transparent bg-linear-to-b from-[#9b6bff] to-[#6a3de8] text-white' : 'btn btn-sm') + '" data-toggle-service title="Komponen ini dikerjakan vendor (bending, chrome, …)">'
            + '<svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 6h11v10H3zm11 4h4l3 3v3h-7M7.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg>'
            + (vendor ? 'Dijasakan' : 'Jasakan') + '</button>'
            + '<span class="ml-auto text-sm font-semibold tabular-nums" data-comp-cost></span>'
            + '<button type="button" class="btn btn-ghost btn-sm text-rose-600" data-remove-component>Hapus</button>'
            + '</div>'
            + '<input type="hidden" name="' + base + '[work_mode]" value="' + (vendor ? 'vendor' : 'internal') + '">'
            + (vendor
                ? '<div class="mt-2 flex flex-wrap items-center gap-2 rounded-[12px] border border-violet-200/70 bg-violet-50/60 p-2.5">'
                    + '<div class="min-w-64 flex-1"><select name="' + base + '[vendor_service_id]" class="input" data-field="cservice_id" aria-label="Jasa vendor" required>' + serviceOptions(comp.vendor_service_id) + '</select></div>'
                    + '<button type="button" class="tile size-10 text-brand-600" data-service-add title="Tambah jasa vendor baru" aria-label="Tambah jasa vendor baru">'
                    + '<svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>'
                    + '<span class="text-[12px] text-ink-soft">' + serviceInfo(comp) + '</span>'
                    + '</div>'
                : '')
            + '<div class="mt-2 space-y-2">' + (lines || '<p class="text-xs text-amber-600">Belum ada bahan.</p>') + '</div>'
            + '<button type="button" class="btn btn-sm mt-2" data-add-line>+ Bahan</button>'
            + '</div>';
    }

    function partHtml(p, part) {
        var names = suggestions[part.name] || [].concat(suggestions.Leheran || [], suggestions.Silincer || []);
        var comps = (part.components || []).map(function (comp, c) { return componentHtml(p, c, comp, part.name); }).join('');
        return '<section class="card space-y-3 p-5" data-part data-p="' + p + '">'
            + '<datalist id="sug-' + p + '">' + names.map(function (n) { return '<option value="' + esc(n) + '">'; }).join('') + '</datalist>'
            + '<div class="flex flex-wrap items-center gap-2">'
            + '<span class="grid size-7 place-items-center rounded-lg bg-brand-50 text-xs font-bold text-brand-700">' + (p + 1) + '</span>'
            + '<input name="parts[' + p + '][name]" value="' + esc(part.name) + '" class="input max-w-64 text-base font-semibold" list="part-names" placeholder="Nama bagian, mis. Leheran" data-field="pname" aria-label="Nama bagian">'
            + '<span class="ml-auto text-sm text-slate-500">Subtotal <strong class="text-slate-900 tabular-nums" data-part-cost></strong></span>'
            + '<button type="button" class="btn btn-ghost btn-sm text-rose-600" data-remove-part>Hapus bagian</button>'
            + '</div>'
            + '<div class="space-y-3">' + (comps || '<p class="text-sm text-slate-500">Belum ada komponen di bagian ini.</p>') + '</div>'
            + '<button type="button" class="btn btn-sm" data-add-component>+ Komponen</button>'
            + '</section>';
    }

    function costHtml(i, cost) {
        return '<div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto_9rem_auto]" data-cost data-i="' + i + '">'
            + '<input name="costs[' + i + '][name]" value="' + esc(cost.name) + '" class="input" list="cost-names" placeholder="mis. Ongkos las" data-field="name" aria-label="Nama ongkos">'
            + '<button type="button" class="tile size-10 text-brand-600" data-cost-save title="Simpan ongkos ini ke Data Ongkos" aria-label="Simpan ke Data Ongkos">'
            + '<svg fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>'
            + '<input name="costs[' + i + '][amount]" type="number" step="any" min="0" value="' + esc(cost.amount) + '" class="input" placeholder="Rp / knalpot" data-field="amount" aria-label="Nominal">'
            + '<button type="button" class="btn btn-ghost px-2 text-rose-600" data-remove-cost aria-label="Hapus ongkos">✕</button>'
            + '</div>';
    }

    function render() {
        partsBox.innerHTML = state.parts.map(function (part, p) { return partHtml(p, part); }).join('');
        costsBox.innerHTML = state.costs.length ? state.costs.map(function (cost, i) { return costHtml(i, cost); }).join('') : '<p class="text-sm text-slate-500">Belum ada ongkos.</p>';
        totals();
    }

    function totals() {
        var material = 0, rows = '', componentServices = 0;
        state.parts.forEach(function (part, p) {
            var partTotal = 0;
            (part.components || []).forEach(function (comp, c) {
                var compTotal = 0;
                (comp.materials || []).forEach(function (line, l) {
                    var cost = lineCost(line, comp.qty);
                    compTotal += cost;
                    var el = partsBox.querySelector('[data-line][data-p="' + p + '"][data-c="' + c + '"][data-l="' + l + '"] [data-line-cost]');
                    if (el) el.textContent = rp(cost);
                });
                if (comp.work_mode === 'vendor') {
                    var jasa = num(comp.qty || 1) * num((servicesById[comp.vendor_service_id] || {}).rate);
                    compTotal += jasa;
                    componentServices += jasa;
                }
                var ce = partsBox.querySelector('[data-component][data-p="' + p + '"][data-c="' + c + '"] [data-comp-cost]');
                if (ce) ce.textContent = rp(compTotal);
                partTotal += compTotal;
            });
            var pe = partsBox.querySelector('[data-part][data-p="' + p + '"] [data-part-cost]');
            if (pe) pe.textContent = rp(partTotal);
            material += partTotal;
            rows += '<div class="flex justify-between"><dt class="text-slate-500">' + esc(part.name || 'Bagian ' + (p + 1)) + '</dt><dd class="tabular-nums">' + rp(partTotal) + '</dd></div>';
        });
        var other = state.costs.reduce(function (sum, c) { return sum + num(c.amount); }, 0);
        var semiNow = form.querySelector('[data-output]').value === 'setengah_jadi';
        var vendorNow = semiNow && (form.querySelector('[data-work-mode]:checked') || {}).value === 'vendor';
        var semiService = servicesById[(form.querySelector('[data-semi-service]') || {}).value];
        var service = (vendorNow && semiService ? num(semiService.rate) : 0) + componentServices;
        material -= componentServices;
        rows += '<div class="flex justify-between border-t border-slate-100 pt-1.5"><dt class="text-slate-500">Total bahan</dt><dd class="tabular-nums">' + rp(material) + '</dd></div>';
        rows += '<div class="flex justify-between"><dt class="text-slate-500">Ongkos</dt><dd class="tabular-nums">' + rp(other) + '</dd></div>';
        if (service > 0) rows += '<div class="flex justify-between"><dt class="text-slate-500">Jasa vendor</dt><dd class="tabular-nums">' + rp(service) + '</dd></div>';
        rows += '<div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>HPP</dt><dd class="tabular-nums text-brand-700">' + rp(material + other + service) + '</dd></div>';
        summary.innerHTML = rows;
    }

    function ref(el) {
        var host = el.closest('[data-line],[data-component],[data-part],[data-cost]');
        return host ? host.dataset : {};
    }

    form.addEventListener('input', function (e) {
        var f = e.target.dataset.field;
        if (!f) return;
        var d = ref(e.target);
        if (f === 'pname') state.parts[d.p].name = e.target.value;
        if (f === 'name' && d.c !== undefined && d.i === undefined) state.parts[d.p].components[d.c].name = e.target.value;
        if (f === 'cqty') state.parts[d.p].components[d.c].qty = e.target.value;

        if (f === 'qty') state.parts[d.p].components[d.c].materials[d.l].qty = e.target.value;
        if (d.i !== undefined) {
            if (f === 'name') {
                state.costs[d.i].name = e.target.value;
                var known = costOptions.filter(function (o) { return o.name.toLowerCase() === e.target.value.trim().toLowerCase(); })[0];
                var amountInput = e.target.closest('[data-cost]').querySelector('[data-field="amount"]');
                if (known && (!amountInput.value || Number(amountInput.value) === 0)) {
                    amountInput.value = known.amount || '';
                    state.costs[d.i].amount = amountInput.value;
                }
            }
            if (f === 'amount') state.costs[d.i].amount = e.target.value;
        }
        totals();
    });

    form.addEventListener('change', function (e) {
        var f = e.target.dataset.field;
        var d = ref(e.target);
        if (f === 'warehouse') {
            var line = state.parts[d.p].components[d.c].materials[d.l];
            line.warehouse = e.target.value;
            line.material_id = '';
            render();
        } else if (f === 'cservice_id') {
            state.parts[d.p].components[d.c].vendor_service_id = e.target.value;
            render();
        } else if (f === 'material_id') {
            state.parts[d.p].components[d.c].materials[d.l].material_id = e.target.value;
            render();
            var q = partsBox.querySelector('[data-line][data-p="' + d.p + '"][data-c="' + d.c + '"][data-l="' + d.l + '"] [data-field="qty"]');
            if (q) q.focus();
        }
    });

    // Tombol "+" di ongkos: simpan nama & tarifnya ke Data Ongkos.
    form.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-cost-save]');
        if (!btn) return;
        var row = btn.closest('[data-cost]');
        var name = row.querySelector('[data-field="name"]').value.trim();
        if (!name) { row.querySelector('[data-field="name"]').focus(); return; }
        btn.disabled = true;
        fetch(@json(route('admin.cost-options.store')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ name: name, amount: row.querySelector('[data-field="amount"]').value || 0 }),
        }).then(function (r) { return r.json().then(function (data) { if (!r.ok) throw data; return data; }); })
            .then(function (data) {
                if (!costOptions.some(function (o) { return o.name.toLowerCase() === data.name.toLowerCase(); })) {
                    costOptions.push({ name: data.name, amount: data.amount });
                    var o = document.createElement('option'); o.value = data.name; document.getElementById('cost-names').appendChild(o);
                }
                btn.classList.add('bg-emerald-50', 'text-emerald-600');
                btn.title = data.message;
                btn.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m5 12 5 5L20 7"/></svg>';
            })
            .catch(function (err) { btn.title = (err && err.message) || 'Gagal menyimpan.'; btn.classList.add('text-rose-600'); })
            .finally(function () { btn.disabled = false; });
    });

    // Tombol "+" di komponen: simpan namanya ke Data Komponen di bagian ini.
    form.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-preset-add]');
        if (!btn) return;
        var d = ref(btn);
        var input = btn.parentElement.querySelector('[data-field="name"]');
        var partName = (state.parts[d.p].name || '').trim();
        var name = input.value.trim();
        if (!name || !partName) { (name ? form.querySelector('[data-part][data-p="' + d.p + '"] [data-field="pname"]') : input).focus(); return; }
        btn.disabled = true;
        fetch(presetUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ kind: 'komponen', part: partName, name: name }),
        }).then(function (r) { return r.json().then(function (data) { if (!r.ok) throw data; return data; }); })
            .then(function (data) {
                var list = suggestions[partName] = suggestions[partName] || [];
                if (list.indexOf(data.name) === -1) list.push(data.name);
                var datalist = document.getElementById('sug-' + d.p);
                if (datalist && ![].some.call(datalist.options, function (o) { return o.value === data.name; })) {
                    var o = document.createElement('option'); o.value = data.name; datalist.appendChild(o);
                }
                btn.classList.add('bg-emerald-50', 'text-emerald-600');
                btn.title = data.message;
                btn.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m5 12 5 5L20 7"/></svg>';
            })
            .catch(function (err) { btn.title = (err && err.message) || 'Gagal menyimpan.'; btn.classList.add('text-rose-600'); })
            .finally(function () { btn.disabled = false; });
    });

    form.addEventListener('click', function (e) {
        // Hanya tombol penyusun formula; tombol dropdown & "+" master data dibiarkan.
        var btn = e.target.closest('button[type="button"]');
        var actions = ['data-add-part', 'data-add-cost', 'data-remove-part', 'data-add-component', 'data-remove-component', 'data-add-line', 'data-remove-line', 'data-remove-cost', 'data-toggle-service'];
        if (!btn || !actions.some(function (a) { return btn.hasAttribute(a); })) return;
        var d = ref(btn);
        if (btn.hasAttribute('data-add-part')) { state.parts.push({ name: '', components: [] }); render(); return; }
        if (btn.hasAttribute('data-add-cost')) { state.costs.push({ name: '', amount: '' }); render(); return; }
        if (btn.hasAttribute('data-remove-part')) {
            if (state.parts[d.p].components.length && !btn.dataset.confirmed) {
                window.appConfirm({ title: 'Hapus bagian?', message: 'Bagian "' + (state.parts[d.p].name || 'ini') + '" beserta komponennya akan dihapus dari formula.', ok: 'Ya, hapus', tone: 'danger' })
                    .then(function (yes) { if (yes) { btn.dataset.confirmed = '1'; btn.click(); } });
                return;
            }
            state.parts.splice(d.p, 1);
        }
        if (btn.hasAttribute('data-add-component')) state.parts[d.p].components.push({ name: '', qty: 1, materials: [{ material_id: '', qty: '' }] });
        if (btn.hasAttribute('data-remove-component')) state.parts[d.p].components.splice(d.c, 1);
        if (btn.hasAttribute('data-toggle-service')) {
            var target = state.parts[d.p].components[d.c];
            target.work_mode = target.work_mode === 'vendor' ? 'internal' : 'vendor';
        }
        if (btn.hasAttribute('data-add-line')) {
            var comp = state.parts[d.p].components[d.c];
            var last = comp.materials[comp.materials.length - 1];
            comp.materials.push({ material_id: '', qty: '', warehouse: last ? (last.warehouse || (byId[last.material_id] || {}).warehouse) : undefined });
        }
        if (btn.hasAttribute('data-remove-line')) state.parts[d.p].components[d.c].materials.splice(d.l, 1);
        if (btn.hasAttribute('data-remove-cost')) state.costs.splice(d.i, 1);
        render();
    });

    // Hasil formula: knalpot (produk jadi) atau barang setengah jadi.
    var output = form.querySelector('[data-output]');
    output.addEventListener('change', function () {
        var semi = output.value === 'setengah_jadi';
        form.querySelectorAll('[data-knalpot-only]').forEach(function (el) { el.classList.toggle('hidden', semi); });
        form.querySelectorAll('[data-semi-only]').forEach(function (el) { el.classList.toggle('hidden', !semi); });
        form.querySelector('#motor_type').required = !semi;
    });

    // Pengerjaan vendor: tampilkan isian vendor & hitung ulang HPP.
    function syncWork() {
        var vendorNow = (form.querySelector('[data-work-mode]:checked') || {}).value === 'vendor';
        form.querySelector('[data-vendor-fields]').classList.toggle('hidden', !vendorNow);
        form.querySelector('[data-semi-service]').required = vendorNow && form.querySelector('[data-output]').value === 'setengah_jadi';
        totals();
    }
    form.querySelectorAll('[data-work-mode]').forEach(function (r) { r.addEventListener('change', syncWork); });
    form.querySelector('[data-semi-service]').addEventListener('change', totals);

    // Tambah jasa vendor baru langsung dari form (komponen atau formula setengah jadi).
    var pop = document.querySelector('[data-service-pop]');
    var popTarget = null;
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-service-add]');
        if (btn) {
            popTarget = btn.dataset.serviceTarget === 'formula' ? { formula: true } : ref(btn);
            var rect = btn.getBoundingClientRect();
            pop.style.top = (rect.bottom + window.scrollY + 8) + 'px';
            pop.style.left = Math.max(12, Math.min(rect.right - 340, window.innerWidth - 352)) + 'px';
            pop.classList.remove('hidden');
            pop.querySelector('[data-error]').classList.add('hidden');
            pop.querySelector('[data-pop-vendor]').focus();
            return;
        }
        if (!pop.classList.contains('hidden') && !pop.contains(e.target) && !e.target.closest('.cselect-panel')) pop.classList.add('hidden');
    });
    pop.querySelector('[data-pop-cancel]').addEventListener('click', function () { pop.classList.add('hidden'); });
    pop.querySelector('[data-pop-save]').addEventListener('click', function () {
        var body = {
            vendor: pop.querySelector('[data-pop-vendor]').value.trim(),
            process: pop.querySelector('[data-pop-process]').value.trim(),
            rate: pop.querySelector('[data-pop-rate]').value,
            unit: pop.querySelector('[data-pop-unit]').value.trim() || 'buah',
        };
        var error = pop.querySelector('[data-error]');
        fetch(@json(route('admin.vendor-services.store')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(body),
        }).then(function (r) { return r.json().then(function (data) { if (!r.ok) throw data; return data; }); })
            .then(function (service) {
                services.push(service);
                servicesById[service.id] = service;
                if (popTarget && popTarget.formula) {
                    var select = form.querySelector('[data-semi-service]');
                    select.add(new Option(service.label, service.id));
                    select.value = service.id;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                } else if (popTarget) {
                    state.parts[popTarget.p].components[popTarget.c].vendor_service_id = String(service.id);
                    render();
                }
                pop.classList.add('hidden');
                pop.querySelectorAll('input').forEach(function (i) { i.value = i.dataset.popUnit !== undefined ? 'buah' : ''; });
            })
            .catch(function (err) {
                error.textContent = (err && (err.errors && Object.values(err.errors)[0][0])) || (err && err.message) || 'Gagal menyimpan.';
                error.classList.remove('hidden');
            });
    });
    form.querySelector('[data-output]').addEventListener('change', syncWork);

    // Type motor tersaring sesuai brand; memilih type mengisi brand-nya.
    var brandSelect = form.querySelector('#motor_brand');
    var typeSelect = form.querySelector('#motor_type');
    function filterTypes() {
        var brand = brandSelect.value;
        Array.prototype.forEach.call(typeSelect.options, function (o) {
            if (!o.value) return;
            o.hidden = !!brand && !!o.dataset.parent && o.dataset.parent !== brand;
        });
        var chosen = typeSelect.selectedOptions[0];
        if (chosen && chosen.hidden) {
            typeSelect.value = '';
            typeSelect.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    brandSelect.addEventListener('change', filterTypes);
    typeSelect.addEventListener('change', function () {
        var chosen = typeSelect.selectedOptions[0];
        if (chosen && chosen.dataset.parent && brandSelect.value !== chosen.dataset.parent) {
            brandSelect.value = chosen.dataset.parent;
            brandSelect.dispatchEvent(new Event('input', { bubbles: true }));
            filterTypes();
        }
    });
    filterTypes();

    // Nama formula ikut type + varian selama belum diketik manual.
    var nameInput = form.querySelector('[data-name]');
    var auto = !nameInput.value;
    nameInput.addEventListener('input', function () { auto = false; });
    form.querySelectorAll('[data-name-source]').forEach(function (el) {
        el.addEventListener('input', function () {
            if (!auto) return;
            nameInput.value = [form.querySelector('#motor_type').value, form.querySelector('#variant').value].filter(Boolean).join(' · ');
        });
    });

    render();
})();
</script>
@endpush
