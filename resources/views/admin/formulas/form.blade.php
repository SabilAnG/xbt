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
                        <p class="text-xs text-slate-500">Biaya di luar bahan per knalpot: jasa las, bending, finishing, packing.</p>
                        <datalist id="cost-names">@foreach ($costSuggestions as $name)<option value="{{ $name }}">@endforeach</datalist>
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
@endsection

@push('scripts')
<script>
(function () {
    var form = document.querySelector('[data-formula-form]');
    var state = { parts: @json($parts), costs: @json($costs) };
    var materials = @json($materials);
    var warehouses = @json($warehouses);
    var suggestions = @json($suggestions);
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
        return '<div class="rounded-xl border border-slate-200 p-3" data-component data-p="' + p + '" data-c="' + c + '">'
            + '<div class="flex flex-wrap items-center gap-2">'
            + '<input name="' + base + '[name]" value="' + esc(comp.name) + '" class="input max-w-56 font-medium" list="' + list + '" placeholder="Nama komponen, mis. P1" data-field="name" aria-label="Nama komponen">'
            + '<span class="text-xs text-slate-500">×</span>'
            + '<input name="' + base + '[qty]" type="number" step="any" min="0" value="' + esc(comp.qty) + '" class="input w-20" data-field="cqty" aria-label="Jumlah per knalpot">'
            + '<span class="text-xs text-slate-500">buah / knalpot</span>'
            + '<span class="ml-auto text-sm font-semibold tabular-nums" data-comp-cost></span>'
            + '<button type="button" class="btn btn-ghost btn-sm text-rose-600" data-remove-component>Hapus</button>'
            + '</div>'
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
            + '<input name="parts[' + p + '][name]" value="' + esc(part.name) + '" class="input max-w-64 text-base font-semibold" placeholder="Nama bagian, mis. Leheran" data-field="pname" aria-label="Nama bagian">'
            + '<span class="ml-auto text-sm text-slate-500">Subtotal <strong class="text-slate-900 tabular-nums" data-part-cost></strong></span>'
            + '<button type="button" class="btn btn-ghost btn-sm text-rose-600" data-remove-part>Hapus bagian</button>'
            + '</div>'
            + '<div class="space-y-3">' + (comps || '<p class="text-sm text-slate-500">Belum ada komponen di bagian ini.</p>') + '</div>'
            + '<button type="button" class="btn btn-sm" data-add-component>+ Komponen</button>'
            + '</section>';
    }

    function costHtml(i, cost) {
        return '<div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto]" data-cost data-i="' + i + '">'
            + '<input name="costs[' + i + '][name]" value="' + esc(cost.name) + '" class="input" list="cost-names" placeholder="mis. Ongkos las" data-field="name" aria-label="Nama ongkos">'
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
        var material = 0, rows = '';
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
                var ce = partsBox.querySelector('[data-component][data-p="' + p + '"][data-c="' + c + '"] [data-comp-cost]');
                if (ce) ce.textContent = rp(compTotal);
                partTotal += compTotal;
            });
            var pe = partsBox.querySelector('[data-part][data-p="' + p + '"] [data-part-cost]');
            if (pe) pe.textContent = rp(partTotal);
            material += partTotal;
            rows += '<div class="flex justify-between"><dt class="text-slate-500">Bahan ' + esc(part.name || 'bagian ' + (p + 1)) + '</dt><dd class="tabular-nums">' + rp(partTotal) + '</dd></div>';
        });
        var other = state.costs.reduce(function (sum, c) { return sum + num(c.amount); }, 0);
        rows += '<div class="flex justify-between border-t border-slate-100 pt-1.5"><dt class="text-slate-500">Total bahan</dt><dd class="tabular-nums">' + rp(material) + '</dd></div>';
        rows += '<div class="flex justify-between"><dt class="text-slate-500">Ongkos</dt><dd class="tabular-nums">' + rp(other) + '</dd></div>';
        rows += '<div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>HPP</dt><dd class="tabular-nums text-brand-700">' + rp(material + other) + '</dd></div>';
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
            if (f === 'name') state.costs[d.i].name = e.target.value;
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
        } else if (f === 'material_id') {
            state.parts[d.p].components[d.c].materials[d.l].material_id = e.target.value;
            render();
            var q = partsBox.querySelector('[data-line][data-p="' + d.p + '"][data-c="' + d.c + '"][data-l="' + d.l + '"] [data-field="qty"]');
            if (q) q.focus();
        }
    });

    form.addEventListener('click', function (e) {
        // Hanya tombol penyusun formula; tombol dropdown & "+" master data dibiarkan.
        var btn = e.target.closest('button[type="button"]');
        var actions = ['data-add-part', 'data-add-cost', 'data-remove-part', 'data-add-component', 'data-remove-component', 'data-add-line', 'data-remove-line', 'data-remove-cost'];
        if (!btn || !actions.some(function (a) { return btn.hasAttribute(a); })) return;
        var d = ref(btn);
        if (btn.hasAttribute('data-add-part')) { state.parts.push({ name: '', components: [] }); render(); return; }
        if (btn.hasAttribute('data-add-cost')) { state.costs.push({ name: '', amount: '' }); render(); return; }
        if (btn.hasAttribute('data-remove-part')) {
            if (state.parts[d.p].components.length && !confirm('Hapus bagian ini beserta komponennya?')) return;
            state.parts.splice(d.p, 1);
        }
        if (btn.hasAttribute('data-add-component')) state.parts[d.p].components.push({ name: '', qty: 1, materials: [{ material_id: '', qty: '' }] });
        if (btn.hasAttribute('data-remove-component')) state.parts[d.p].components.splice(d.c, 1);
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
