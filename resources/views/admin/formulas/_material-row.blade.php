@php
    $n = "materials[{$i}]";
    $val = fn ($key, $default = null) => $row[$key] ?? $default;
    $num = fn ($key) => isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null ? (float) $row[$key] : null;
    $modes = \App\Models\FormulaMaterial::INPUT_MODES;
@endphp
<div class="material-row card p-4" data-row>
    <input type="hidden" name="{{ $n }}[id]" value="{{ $val('id') }}">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <label class="label">Bahan</label>
            <select name="{{ $n }}[material_id]" class="input" data-role="material">
                <option value="">— pilih bahan —</option>
                @foreach ($materials as $material)
                    <option value="{{ $material->id }}" @selected((string) $val('material_id') === (string) $material->id)>{{ $material->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-2">
            <label class="label">Merek</label>
            <select name="{{ $n }}[brand]" class="input">
                <option value="">Semua</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand }}" @selected($val('brand') === $brand)>{{ $brand }}</option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-3">
            <label class="label">Bagian</label>
            <select name="{{ $n }}[bom_group]" class="input">
                @foreach ($groups as $key => $label)
                    <option value="{{ $key }}" @selected($val('bom_group', 'other') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-3">
            <label class="label">Cara isi</label>
            <select name="{{ $n }}[input_mode]" class="input" data-role="mode">
                @foreach ($modes as $key => $label)
                    <option value="{{ $key }}" @selected($val('input_mode', 'direct') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="lg:col-span-2" data-show="length rect">
            <label class="label">Panjang (mm)</label>
            <input type="number" step="any" min="0" name="{{ $n }}[piece_length_mm]" value="{{ $num('piece_length_mm') }}" class="input">
        </div>
        <div class="lg:col-span-2" data-show="rect">
            <label class="label">Lebar (mm)</label>
            <input type="number" step="any" min="0" name="{{ $n }}[piece_width_mm]" value="{{ $num('piece_width_mm') }}" class="input">
        </div>
        <div class="lg:col-span-2" data-show="circle">
            <label class="label">Diameter Ø (mm)</label>
            <input type="number" step="any" min="0" name="{{ $n }}[piece_diameter_mm]" value="{{ $num('piece_diameter_mm') }}" class="input">
        </div>
        <div class="lg:col-span-2" data-show="direct">
            <label class="label">Jumlah per resep <span class="font-normal text-slate-400" data-role="unit"></span></label>
            <input type="number" step="any" min="0" name="{{ $n }}[qty]" value="{{ $num('qty') }}" class="input">
        </div>
        <div class="lg:col-span-2" data-show="length rect circle">
            <label class="label">Berapa potong</label>
            <input type="number" step="any" min="0" name="{{ $n }}[piece_count]" value="{{ $num('piece_count') ?? 1 }}" class="input">
        </div>
        <div class="lg:col-span-2">
            <label class="label">Susut (%)</label>
            <input type="number" step="any" min="0" max="100" name="{{ $n }}[waste_percent]" value="{{ $num('waste_percent') ?? 0 }}" class="input">
        </div>
        <div class="flex items-end pb-2 lg:col-span-2" data-role="nesting">
            <label class="flex items-center gap-2 text-xs text-slate-700" title="Matikan bila diambil dari sisa plat.">
                <input type="hidden" name="{{ $n }}[use_nesting]" value="0">
                <input type="checkbox" name="{{ $n }}[use_nesting]" value="1" class="rounded border-slate-300" @checked($val('use_nesting', true))> Hitung sisa potong
            </label>
        </div>
        <div class="sm:col-span-2 lg:col-span-4">
            <label class="label">Catatan</label>
            <input name="{{ $n }}[notes]" value="{{ $val('notes') }}" class="input">
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs text-slate-600">
        <div class="space-x-3">
            @if ($saved)
                <span>Kebutuhan: <strong>{{ $saved->displayEffective() }}</strong></span>
                @if ($label = $saved->cuttingLabel())<span>{{ $label }}</span>@endif
                <span>Biaya: <strong>{{ rupiah($saved->subtotal()) }}</strong></span>
                @isset($producedFor)
                    @php $produced = $producedFor($saved->brand); @endphp
                    <span title="Sama dengan kolom Knalpot Diproduksi / Total Terpakai di Excel">Diproduksi {{ qty($produced) }} → terpakai <strong>{{ qty($produced * (float) $saved->qty) }}</strong></span>
                @endisset
            @else
                <span class="text-slate-400">Kebutuhan & biaya tampil setelah disimpan.</span>
            @endif
        </div>
        <button type="button" class="btn btn-danger btn-sm" data-remove>Hapus baris</button>
    </div>
</div>
