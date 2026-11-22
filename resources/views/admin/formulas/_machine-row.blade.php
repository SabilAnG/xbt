@php
    $n = "machines[{$i}]";
    $val = fn ($key, $default = null) => $row[$key] ?? $default;
@endphp
<div class="card p-4" data-row>
    <input type="hidden" name="{{ $n }}[id]" value="{{ $val('id') }}">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <label class="label">Mesin</label>
            <select name="{{ $n }}[machine_id]" class="input">
                <option value="">— pilih mesin —</option>
                @foreach ($machines as $machine)
                    <option value="{{ $machine->id }}" @selected((string) $val('machine_id') === (string) $machine->id)>{{ $machine->name }} — {{ $machine->displayHourlyCost() }}</option>
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
        <div class="lg:col-span-2">
            <label class="label">Waktu (menit)</label>
            <input type="number" step="any" min="0" name="{{ $n }}[minutes]" value="{{ $val('minutes') !== null ? (float) $val('minutes') : 0 }}" class="input">
        </div>
        <div class="flex items-end gap-2 lg:col-span-2">
            <div class="flex-1 pb-2 text-right text-xs text-slate-600">{{ $saved ? rupiah($saved->subtotal()) : '' }}</div>
            <button type="button" class="btn btn-danger btn-sm mb-1" data-remove>Hapus</button>
        </div>
        <div class="sm:col-span-2 lg:col-span-12">
            <input name="{{ $n }}[notes]" value="{{ $val('notes') }}" class="input" placeholder="Catatan (opsional)">
        </div>
    </div>
</div>
