@extends('layouts.admin', ['title' => 'Mutasi komponen', 'subtitle' => 'Gudang Setengah Jadi: catat komponen yang selesai dibuat, dipakai rakit, atau koreksi hitung fisik.'])

@php
    $bom = $components->mapWithKeys(fn ($c) => [$c->id => [
        'unit' => $c->unit,
        'stock' => (float) $c->stock,
        'lines' => $c->materials->map(fn ($l) => ['name' => $l->material?->name, 'unit' => $l->material?->unit, 'qty' => (float) $l->qty, 'stock' => (float) $l->material?->stock])->values(),
    ]]);
@endphp

@section('content')
    @if ($components->isEmpty())
        <div class="card max-w-2xl p-8 text-center">
            <p class="text-sm font-medium text-slate-900">Belum ada komponen</p>
            <p class="mt-1 text-sm text-slate-500">Daftarkan komponen knalpot dulu di Master Komponen.</p>
            <a href="{{ route('admin.components.create') }}" class="btn btn-primary mt-4">+ Komponen</a>
        </div>
    @else
        <form method="POST" action="{{ route('admin.warehouses.movements.store') }}" class="card max-w-2xl space-y-6 p-6" data-movement>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.select name="component_id" label="Komponen" :options="$components->mapWithKeys(fn ($c) => [$c->id => $c->code.' · '.$c->name])" :value="$selected" required class="sm:col-span-2" />
                <x-admin.select name="type" label="Jenis" :options="\App\Models\ComponentMovement::TYPES" :value="$type" :placeholder="false" required />
                <x-admin.field name="qty" label="Qty" type="number" step="any" required hint="Penyesuaian: isi minus untuk mengurangi." />
                <x-admin.field name="moved_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                <x-admin.field name="notes" label="Keterangan" placeholder="mis. Hasil las tukang Budi" />
            </div>

            <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4" data-consume-box>
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="hidden" name="consume_materials" value="0">
                    <input type="checkbox" name="consume_materials" value="1" class="mt-0.5" @checked(old('consume_materials', true))>
                    <span><strong>Potong stok bahan baku</strong> sesuai bahan penyusun komponen.</span>
                </label>
                <div class="text-xs text-slate-600" data-consume-preview></div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                <a href="{{ route('admin.warehouses.show', 'setengah-jadi') }}" class="btn">Batal</a>
                <button class="btn btn-primary">Simpan</button>
            </div>
        </form>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.querySelector('[data-movement]');
        if (!form) return;
        var bom = @json($bom);
        var box = form.querySelector('[data-consume-box]');
        var preview = form.querySelector('[data-consume-preview]');
        var fmt = function (n) { return (Math.round(n * 1000) / 1000).toLocaleString('id-ID'); };
        var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };

        function render() {
            var c = bom[form.component_id.value];
            var isIn = form.type.value === 'masuk';
            box.classList.toggle('hidden', !isIn);
            if (!c) { preview.innerHTML = ''; return; }
            var qty = parseFloat(form.qty.value) || 0;
            if (!c.lines.length) {
                preview.innerHTML = '<span class="text-amber-700">Komponen ini belum punya bahan penyusun, jadi tidak ada bahan yang terpotong.</span>';
                return;
            }
            preview.innerHTML = '<p class="mb-1 font-medium">Bahan yang terpotong:</p><ul class="space-y-0.5">' + c.lines.map(function (l) {
                var need = l.qty * qty;
                var short = need > l.stock;
                return '<li class="flex justify-between gap-3"><span>' + esc(l.name) + '</span><span class="tabular-nums ' + (short ? 'text-rose-600 font-medium' : '') + '">' +
                    fmt(need) + ' ' + esc(l.unit) + ' <span class="text-slate-400">(stok ' + fmt(l.stock) + ')</span></span></li>';
            }).join('') + '</ul>';
        }

        ['change', 'input'].forEach(function (ev) { form.addEventListener(ev, render); });
        render();
    })();
</script>
@endpush
