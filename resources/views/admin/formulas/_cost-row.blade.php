@php
    $n = "costs[{$i}]";
    $val = fn ($key, $default = null) => $row[$key] ?? $default;
@endphp
<div class="cost-row card p-4" data-row>
    <input type="hidden" name="{{ $n }}[id]" value="{{ $val('id') }}">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <label class="label">Proses</label>
            <select name="{{ $n }}[cost_component_id]" class="input" data-role="component">
                <option value="">— pilih proses —</option>
                @foreach ($components as $component)
                    <option value="{{ $component->id }}" @selected((string) $val('cost_component_id') === (string) $component->id)>{{ $component->name }} — {{ $component->displayRate() }}</option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-3">
            <label class="label">Bagian</label>
            <select name="{{ $n }}[bom_group]" class="input">
                @foreach ($groups as $key => $label)
                    <option value="{{ $key }}" @selected($val('bom_group', 'finishing') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-2" data-role="minutes">
            <label class="label">Waktu (menit)</label>
            <input type="number" step="any" min="0" name="{{ $n }}[minutes]" value="{{ $val('minutes') !== null ? (float) $val('minutes') : 0 }}" class="input">
        </div>
        <div class="lg:col-span-2" data-role="qty">
            <label class="label">Jumlah</label>
            <input type="number" step="any" min="0" name="{{ $n }}[qty]" value="{{ $val('qty') !== null ? (float) $val('qty') : 1 }}" class="input">
        </div>
        <div class="flex items-end gap-2 lg:col-span-2">
            <div class="flex-1 pb-2 text-right text-xs text-zinc-600">{{ $saved ? rupiah($saved->subtotal()) : '' }}</div>
            <button type="button" class="btn btn-danger btn-sm mb-1" data-remove>Hapus</button>
        </div>
        <div class="sm:col-span-2 lg:col-span-12">
            <input name="{{ $n }}[notes]" value="{{ $val('notes') }}" class="input" placeholder="Catatan (opsional)">
        </div>
    </div>
</div>
