@extends('layouts.admin', ['title' => 'Penyesuaian stok'])

@section('content')
    <form method="POST" action="{{ route('admin.adjustments.store') }}" class="card max-w-3xl space-y-4 p-6" data-adjust-form>
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field name="adjusted_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
            <x-admin.select name="reason" label="Alasan" :options="\App\Models\MaterialAdjustment::REASONS" value="stok_awal" :placeholder="false" required />
            <div class="sm:col-span-2">
                <label for="material_id" class="label">Bahan<span class="text-red-500"> *</span></label>
                <select id="material_id" name="material_id" class="input" required data-material>
                    <option value="">— pilih bahan —</option>
                    @foreach ($materials->groupBy(fn ($m) => $m->warehouse->name) as $warehouse => $group)
                        <optgroup label="{{ $warehouse }}">
                            @foreach ($group as $material)
                                <option value="{{ $material->id }}" data-pipe="{{ $material->isPipe() ? 1 : 0 }}" data-unit="{{ $material->stockUnit() }}" data-warehouse="{{ $material->warehouse_id }}"
                                    data-bar="{{ (float) $material->bar_length_cm }}" data-cost="{{ round($material->unitCost(), 4) }}"
                                    @selected((string) old('material_id', $selected) === (string) $material->id)>{{ $material->label() }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('material_id')<p class="error">{{ $message }}</p>@enderror
            </div>
            <x-admin.select name="direction" label="Arah" :options="['in' => 'Masuk (tambah stok)', 'out' => 'Keluar (kurangi / afkir)']" value="in" :placeholder="false" required data-direction />
            <div data-show="warehouse">
                <x-admin.select name="warehouse_id" label="Gudang" :options="$warehouses" :placeholder="false" data-warehouse
                    hint="Pipa: batang utuh ke Bahan Baku, potongan ke Stok Sisa." />
            </div>
            <div data-show="qty">
                <x-admin.field name="qty" label="Jumlah" type="number" step="any" min="0" data-qty-label />
            </div>
            <div data-show="length">
                <x-admin.field name="length_cm" label="Panjang tiap batang/potongan (cm)" type="number" step="any" min="0" data-length />
            </div>
            <div data-show="cost">
                <x-admin.field name="unit_cost" label="Harga per satuan (Rp)" type="number" step="any" min="0" data-cost hint="Kosongkan = harga rata-rata bahan saat ini." />
            </div>
            <div data-show="piece" class="sm:col-span-2">
                <label for="pipe_piece_id" class="label">Potongan yang diafkir</label>
                <select id="pipe_piece_id" name="pipe_piece_id" class="input" data-piece></select>
                @error('pipe_piece_id')<p class="error">{{ $message }}</p>@enderror
            </div>
            <x-admin.field name="notes" label="Catatan" class="sm:col-span-2" />
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.adjustments.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.querySelector('[data-adjust-form]');
        var pieces = @json($pieces);
        var material = form.querySelector('[data-material]');
        var direction = form.querySelector('[data-direction]');
        var offcutId = @json(\App\Models\Warehouse::forType('sisa_produksi')->id);

        function show(name, visible) { form.querySelector('[data-show="' + name + '"]').classList.toggle('hidden', !visible); }

        function sync(changedMaterial) {
            var opt = material.selectedOptions[0];
            var pipe = opt && opt.dataset.pipe === '1';
            var out = direction.value === 'out';
            show('warehouse', !(pipe && out));
            show('qty', !(pipe && out));
            show('length', pipe && !out);
            show('cost', !out);
            show('piece', pipe && out);

            var wh = form.querySelector('[data-warehouse]');
            Array.prototype.forEach.call(wh.options, function (o) {
                o.hidden = !opt || !opt.value ? false : !(o.value === opt.dataset.warehouse || (pipe && String(o.value) === String(offcutId)));
            });
            if (changedMaterial && opt && opt.value) {
                wh.value = opt.dataset.warehouse;
                if (pipe) form.querySelector('[data-length]').value = opt.dataset.bar;
            }

            var qtyLabel = form.querySelector('label[for="qty"]');
            qtyLabel.firstChild.textContent = pipe ? 'Jumlah batang / potongan' : 'Jumlah' + (opt && opt.value ? ' (' + opt.dataset.unit + ')' : '');
            var costLabel = form.querySelector('label[for="unit_cost"]');
            costLabel.firstChild.textContent = pipe ? 'Harga per cm (Rp)' : 'Harga per satuan (Rp)';
            form.querySelector('[data-cost]').placeholder = opt && opt.value ? opt.dataset.cost : '';

            var select = form.querySelector('[data-piece]');
            var list = (opt && pieces[opt.value]) || [];
            select.innerHTML = list.length ? list.map(function (p) { return '<option value="' + p.id + '">' + p.label + '</option>'; }).join('') : '<option value="">Tidak ada potongan tersedia</option>';
        }

        material.addEventListener('change', function () { sync(true); });
        direction.addEventListener('change', function () { sync(false); });
        sync(!{{ old('material_id') ? 'true' : 'false' }});
    })();
</script>
@endpush
