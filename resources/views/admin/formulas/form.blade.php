@php
    $tab = request('tab', 'identitas');
    $groups = \App\Models\FormulaMaterial::BOM_GROUPS;

    // Baris yang ditampilkan: isian lama (setelah gagal validasi) atau data tersimpan.
    $materialRows = collect(old('materials', $formula->materials->map(fn ($line) => [
        'id' => $line->id,
        'material_id' => $line->material_id, 'brands' => $line->brandList(), 'bom_group' => $line->bom_group,
        'input_mode' => $line->input_mode, 'piece_length_mm' => $line->piece_length_mm, 'piece_width_mm' => $line->piece_width_mm,
        'piece_diameter_mm' => $line->piece_diameter_mm, 'qty' => $line->qty, 'piece_count' => $line->piece_count,
        'waste_percent' => $line->waste_percent, 'use_nesting' => $line->use_nesting, 'notes' => $line->notes,
    ])->all()))->values();
    $costRows = collect(old('costs', $formula->costs->map(fn ($line) => [
        'id' => $line->id, 'cost_component_id' => $line->cost_component_id, 'bom_group' => $line->bom_group,
        'minutes' => $line->minutes, 'qty' => $line->qty, 'notes' => $line->notes,
    ])->all()))->values();
    $machineRows = collect(old('machines', $formula->machines->map(fn ($line) => [
        'id' => $line->id, 'machine_id' => $line->machine_id, 'bom_group' => $line->bom_group,
        'minutes' => $line->minutes, 'notes' => $line->notes,
    ])->all()))->values();

    $savedMaterials = $formula->materials->keyBy('id');
    $savedCosts = $formula->costs->keyBy('id');
    $savedMachines = $formula->machines->keyBy('id');

    $materialMeta = $materials->mapWithKeys(fn ($m) => [$m->id => [
        'unit' => $m->baseUnit(), 'type' => $m->dimension_type, 'modes' => \App\Models\FormulaMaterial::modesFor($m->dimension_type),
    ]]);
    $componentMeta = $components->mapWithKeys(fn ($c) => [$c->id => ['hourly' => $c->isHourly(), 'minutes' => (float) $c->default_minutes]]);

    // "Knalpot diproduksi" per baris: semua produk bermerek bila baris berlaku untuk semua merek,
    // selain itu jumlah produksi merek-merek yang disebut baris itu.
    $producedFor = fn (array $brands) => $brands
        ? (float) collect($brands)->sum(fn ($b) => (float) ($producedByBrand[$b] ?? 0))
        : (float) $producedByBrand->filter(fn ($q, $b) => trim((string) $b) !== '')->sum();
@endphp

@extends('layouts.admin', ['title' => $formula->exists ? 'Formula: '.$formula->name : 'Formula baru', 'subtitle' => 'Bahan + proses kerja + mesin + overhead = HPP per unit'])

@section('actions')
    <a href="{{ route('admin.formulas.index') }}" class="btn">Kembali</a>
@endsection

@section('content')
    <form method="POST" action="{{ $formula->exists ? route('admin.formulas.update', $formula) : route('admin.formulas.store') }}" class="space-y-6" id="formula-form">
        @csrf
        @if ($formula->exists) @method('PUT') @endif
        <input type="hidden" name="tab" value="{{ $tab }}" id="current-tab">

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
        @endif

        <nav class="flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
            @foreach (['identitas' => 'Identitas', 'bahan' => 'Bahan ('.$materialRows->count().')', 'proses' => 'Proses & Mesin ('.($costRows->count() + $machineRows->count()).')', 'hasil' => 'Hasil / HPP'] as $key => $label)
                <button type="button" data-tab="{{ $key }}" class="tab-button shrink-0 rounded-lg px-4 py-2 text-sm font-medium whitespace-nowrap">{{ $label }}</button>
            @endforeach
        </nav>

        {{-- ------------------------------------------------------ Identitas --}}
        <section data-panel="identitas" class="space-y-6">
            <div class="card grid gap-4 p-6 sm:grid-cols-6">
                <x-admin.field name="name" label="Nama formula" :value="$formula->name" required class="sm:col-span-4" placeholder="Mio M3 125 — STD Racing V1" />
                <x-admin.field name="code" label="Kode" :value="$formula->code" class="sm:col-span-2" hint="Kosongkan untuk dibuat dari nama." />
                <x-admin.select name="motorcycle_model_id" label="Type motor" :value="$formula->motorcycle_model_id" class="sm:col-span-3"
                    :options="$motorcycleModels->mapWithKeys(fn ($m) => [$m->id => trim(($m->brand?->name ? $m->brand->name.' ' : '').$m->name)])"
                    :hint="$motorcycleModels->isEmpty() ? 'Belum ada data — isi di menu Type Motor.' : null" />
                <x-admin.field name="output_qty" label="Hasil per resep" type="number" step="any" min="0" :value="(float) $formula->output_qty" required class="sm:col-span-1" />
                <x-admin.field name="output_unit" label="Satuan hasil" :value="$formula->output_unit" required class="sm:col-span-1" />
                <div class="flex items-end pb-2 sm:col-span-1">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $formula->is_active ?? true))> Aktif
                    </label>
                </div>
                <x-admin.field name="notes" label="Catatan" type="textarea" :value="$formula->notes" class="sm:col-span-6" />
            </div>

            @if ($formula->exists)
                <div class="card overflow-hidden">
                    <div class="card-header"><h3 class="card-title">Produk stok yang memakai formula ini ({{ $products->count() }})</h3></div>
                    <div class="flex flex-wrap gap-2 p-4">
                        @forelse ($products as $item)
                            <a href="{{ route('admin.items.show', $item) }}" class="rounded-full bg-slate-100 px-3 py-1 text-xs hover:bg-brand-50">{{ $item->name }}</a>
                        @empty
                            <p class="text-sm text-slate-500">Belum ada. Pilih formula ini di form produk (Stok Produk → Resep BOM).</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </section>

        {{-- ---------------------------------------------------------- Bahan --}}
        <section data-panel="bahan" class="space-y-4">
            <p class="text-sm text-slate-600">Isi ukuran apa adanya — pipa dalam mm, plat dalam ukuran potongan, endcap cukup diameternya. Sistem menghitung luas, sisa potong, dan biayanya.
                Kolom <strong>Merek</strong>: tidak dicentang = berlaku untuk semua produk bermerek; centang satu atau beberapa merek = hanya produk merek itu. Merek dikelola di menu <a href="{{ route('admin.brands.index') }}" class="link">Merek</a>.</p>
            <div id="material-rows" class="space-y-3">
                @foreach ($materialRows as $i => $row)
                    @include('admin.formulas._material-row', ['i' => $i, 'row' => $row, 'saved' => $savedMaterials->get($row['id'] ?? null)])
                @endforeach
            </div>
            <button type="button" class="btn" data-add="material">+ Tambah bahan</button>
        </section>

        {{-- -------------------------------------------------- Proses & Mesin --}}
        <section data-panel="proses" class="space-y-6">
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-slate-900">Proses kerja <span class="font-normal text-slate-500">— isi menit untuk tarif per jam, jumlah untuk borongan. Tarif dari <a href="{{ route('admin.masters.index', 'komponen-biaya') }}" class="link">Komponen Biaya</a>.</span></h3>
                <div id="cost-rows" class="space-y-3">
                    @foreach ($costRows as $i => $row)
                        @include('admin.formulas._cost-row', ['i' => $i, 'row' => $row, 'saved' => $savedCosts->get($row['id'] ?? null)])
                    @endforeach
                </div>
                <button type="button" class="btn" data-add="cost">+ Tambah proses</button>
            </div>
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-slate-900">Pemakaian mesin <span class="font-normal text-slate-500">— berapa menit mesin menyala. Biaya per jam dari <a href="{{ route('admin.masters.index', 'mesin') }}" class="link">Mesin</a>.</span></h3>
                <div id="machine-rows" class="space-y-3">
                    @foreach ($machineRows as $i => $row)
                        @include('admin.formulas._machine-row', ['i' => $i, 'row' => $row, 'saved' => $savedMachines->get($row['id'] ?? null)])
                    @endforeach
                </div>
                <button type="button" class="btn" data-add="machine">+ Tambah mesin</button>
            </div>
        </section>

        {{-- ---------------------------------------------------------- Hasil --}}
        <section data-panel="hasil" class="space-y-6">
            @if ($formula->exists)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">Angka dihitung dari data tersimpan. Simpan dulu bila baru mengubah isian.</p>
                    @if ($formulaBrands->count() > 0)
                        <div class="flex items-center gap-1 text-sm">
                            <span class="mr-1 text-xs text-slate-500">HPP untuk merek:</span>
                            @foreach ($formulaBrands as $brand)
                                <a href="{{ route('admin.formulas.edit', [$formula, 'tab' => 'hasil', 'merek' => $brand]) }}"
                                    @class(['rounded-lg px-3 py-1 text-xs font-medium', 'bg-slate-900 text-white' => $brand === $hppBrand, 'bg-white ring-1 ring-slate-200' => $brand !== $hppBrand])>{{ $brand }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
                @include('admin.formulas._hpp', ['formula' => $hppFormula])
            @else
                <div class="card p-8 text-center text-sm text-slate-500">Simpan formula ini dulu untuk melihat HPP, harga jual, dan kapasitasnya.</div>
            @endif
        </section>

        <div class="flex items-center justify-between gap-2 border-t border-slate-200 pt-4">
            <div>
                @if ($formula->exists)
                    <button form="delete-formula" class="btn btn-danger">Hapus formula</button>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.formulas.index') }}" class="btn">Batal</a>
                <button class="btn btn-primary">Simpan formula</button>
            </div>
        </div>
    </form>

    @if ($formula->exists)
        <form id="delete-formula" method="POST" action="{{ route('admin.formulas.destroy', $formula) }}" onsubmit="return confirm('Hapus formula ini?')">
            @csrf
            @method('DELETE')
        </form>
    @endif

    <template id="tpl-material">@include('admin.formulas._material-row', ['i' => '__INDEX__', 'row' => ['bom_group' => 'other', 'input_mode' => 'direct', 'piece_count' => 1, 'waste_percent' => 0, 'qty' => 1, 'use_nesting' => true], 'saved' => null])</template>
    <template id="tpl-cost">@include('admin.formulas._cost-row', ['i' => '__INDEX__', 'row' => ['bom_group' => 'finishing', 'qty' => 1], 'saved' => null])</template>
    <template id="tpl-machine">@include('admin.formulas._machine-row', ['i' => '__INDEX__', 'row' => ['bom_group' => 'other'], 'saved' => null])</template>

    <script>
        (() => {
            const materialMeta = @json($materialMeta);
            const componentMeta = @json($componentMeta);

            // Tab: tetap di tab yang sama setelah simpan.
            const setTab = (name) => {
                document.querySelectorAll('[data-panel]').forEach(p => p.hidden = p.dataset.panel !== name);
                document.querySelectorAll('.tab-button').forEach(b => {
                    const active = b.dataset.tab === name;
                    b.classList.toggle('bg-slate-900', active);
                    b.classList.toggle('text-white', active);
                    b.classList.toggle('text-slate-600', !active);
                });
                document.getElementById('current-tab').value = name;
            };
            document.querySelectorAll('.tab-button').forEach(b => b.addEventListener('click', () => setTab(b.dataset.tab)));
            setTab(@json($tab));

            // Bahan: pilihan "cara isi" mengikuti tipe bahan, isian ukuran mengikuti cara isi.
            const refreshMaterialRow = (row, resetMode) => {
                const meta = materialMeta[row.querySelector('[data-role=material]').value];
                const modeSelect = row.querySelector('[data-role=mode]');
                if (meta) {
                    const current = modeSelect.value;
                    modeSelect.innerHTML = Object.entries(meta.modes).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
                    modeSelect.value = (!resetMode && meta.modes[current]) ? current : Object.keys(meta.modes)[0];
                    row.querySelectorAll('[data-role=unit]').forEach(el => el.textContent = meta.unit);
                    row.querySelector('[data-role=nesting]').hidden = !(meta.type === 'sheet' && ['rect', 'circle'].includes(modeSelect.value));
                }
                const mode = modeSelect.value;
                row.querySelectorAll('[data-show]').forEach(el => el.hidden = !el.dataset.show.split(' ').includes(mode));
            };
            const refreshCostRow = (row) => {
                const meta = componentMeta[row.querySelector('[data-role=component]').value];
                const hourly = meta ? meta.hourly : true;
                row.querySelector('[data-role=minutes]').hidden = !hourly;
                row.querySelector('[data-role=qty]').hidden = hourly;
            };
            const wire = (root) => {
                root.querySelectorAll('.material-row').forEach(row => {
                    refreshMaterialRow(row, false);
                    row.querySelector('[data-role=material]').addEventListener('change', () => refreshMaterialRow(row, true));
                    row.querySelector('[data-role=mode]').addEventListener('change', () => refreshMaterialRow(row, false));
                });
                root.querySelectorAll('.cost-row').forEach(row => {
                    refreshCostRow(row);
                    row.querySelector('[data-role=component]').addEventListener('change', (e) => {
                        const meta = componentMeta[e.target.value];
                        if (meta && meta.hourly) row.querySelector('[data-role=minutes] input').value = meta.minutes;
                        refreshCostRow(row);
                    });
                });
                root.querySelectorAll('[data-remove]').forEach(btn => btn.addEventListener('click', () => btn.closest('[data-row]').remove()));
            };
            wire(document);

            let counter = 1000;
            document.querySelectorAll('[data-add]').forEach(btn => btn.addEventListener('click', () => {
                const type = btn.dataset.add;
                const html = document.getElementById('tpl-' + type).innerHTML.replaceAll('__INDEX__', counter++);
                const holder = document.createElement('div');
                holder.innerHTML = html.trim();
                wire(holder);
                document.getElementById(type + '-rows').appendChild(holder.firstElementChild);
            }));
        })();
    </script>
@endsection
