@extends('layouts.admin', ['title' => $material->exists ? 'Ubah item' : 'Item baru', 'subtitle' => 'Pipa dicatat per batang dan dipotong per cm. Bahan lain bisa punya satuan beli & satuan pakai berbeda (mis. plat: beli per lembar, pakai per cm²). Harga beli dipakai formula selama belum ada stok.'])

@php $isPipe = old('measure', $material->measure) === \App\Models\Material::LENGTH; @endphp

@section('content')
    <form method="POST" action="{{ $material->exists ? route('admin.materials.update', $material) : route('admin.materials.store') }}" class="max-w-4xl space-y-6" data-material-form>
        @csrf
        @if ($material->exists) @method('PUT') @endif

        <section class="card space-y-4 p-6">
            <h2 class="card-title">Data bahan</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.select name="measure" label="Cara hitung" :options="\App\Models\Material::MEASURES" :value="$material->measure" :placeholder="false" required data-measure />
                <x-admin.select name="warehouse_id" label="Gudang asal" :options="$warehouses" :value="$material->warehouse_id" :placeholder="false" required
                    hint="Bahan Baku = mentah; Setengah Jadi = beli jadi / hasil proses; Aksesoris = baut, stiker, dus." />
                <x-admin.field name="code" label="Kode" :value="$material->code" placeholder="Kosongkan = otomatis" />
                <x-admin.field name="name" label="Nama bahan" :value="$material->name" required class="sm:col-span-2" placeholder="mis. Pipa Stainless 201 / Pureng Mio" />
                <div>
                    <label for="category" class="label">Kategori</label>
                    <input id="category" name="category" value="{{ old('category', $material->category?->name) }}" class="input" list="material-categories" placeholder="mis. Pipa, Plendes">
                    <datalist id="material-categories">@foreach ($categories as $category)<option value="{{ $category }}">@endforeach</datalist>
                </div>
                <x-admin.field name="spec" label="Ukuran / spesifikasi" :value="$material->spec" class="sm:col-span-2" placeholder="mis. Ø 1¼&quot; · tebal 1,2 mm" />
            </div>
        </section>

        <section data-unit-only @class(['card space-y-4 p-6', 'hidden' => $isPipe])>
            <div>
                <h2 class="card-title">Satuan & konversi</h2>
                <p class="text-xs text-slate-500">Satuan beli dipakai di Pembelian. Satuan pakai dipakai di stok & formula. Kosongkan satuan pakai bila sama dengan satuan beli. Daftar satuan diatur di <a href="{{ route('admin.units.index') }}" class="link" target="_blank">Master Satuan</a>.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.select name="unit" label="Satuan beli" :options="$units->mapWithKeys(fn ($u) => [$u => $u])" :value="$material->unit" :placeholder="false" data-unit />
                <x-admin.select name="use_unit" label="Satuan pakai" :options="$units->mapWithKeys(fn ($u) => [$u => $u])" :value="$material->use_unit" placeholder="— sama dengan satuan beli —" data-use-unit />
                <x-admin.field name="conversion" label="Isi per 1 satuan beli" type="number" step="any" min="0" :value="$material->hasConversion() ? (float) $material->conversion : null"
                    placeholder="mis. 28800" data-conversion />
            </div>
            <p class="text-sm font-medium text-slate-700" data-conversion-label></p>
        </section>

        <section data-pipe-only @class(['card space-y-4 p-6', 'hidden' => ! $isPipe])>
            <h2 class="card-title">Ukuran batang pipa</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.field name="bar_length_cm" label="Panjang 1 batang (cm)" type="number" step="any" min="0" :value="$material->bar_length_cm ? (float) $material->bar_length_cm : null"
                    hint="Panjang standar saat dibeli, mis. 600 cm." />
                <x-admin.field name="min_reusable_cm" label="Sisa minimal dipakai lagi (cm)" type="number" step="any" min="0" :value="(float) $material->min_reusable_cm"
                    hint="Sisa potong lebih pendek dari ini = afkir, tidak masuk Stok Sisa." />
            </div>
        </section>

        <section class="card space-y-4 p-6">
            <h2 class="card-title">Harga & stok</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.field name="last_price" type="number" step="any" min="0" :value="(float) $material->last_price"
                    :label="$isPipe ? 'Harga beli per batang (Rp)' : 'Harga beli per satuan beli (Rp)'"
                    hint="Diperbarui otomatis setiap ada pembelian." />
                <x-admin.field name="min_stock" type="number" step="any" min="0" :value="(float) $material->min_stock"
                    :label="$isPipe ? 'Stok minimum (cm)' : 'Stok minimum (satuan pakai)'" hint="Status MENIPIS bila stok di bawah ini." />
                @if ($material->exists)
                    <div>
                        <p class="label">Harga rata-rata stok</p>
                        <p class="py-2 text-sm font-semibold">{{ rupiah($material->unitCost()) }} <span class="font-normal text-slate-500">/ {{ $material->stockUnit() }}</span></p>
                    </div>
                @endif
            </div>
            <x-admin.field name="notes" label="Catatan" type="textarea" :value="$material->notes" />
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $material->is_active))> Aktif
            </label>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ $material->exists ? route('admin.materials.show', $material) : route('admin.materials.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>

    @if ($material->exists && ! $material->movements()->exists())
        <div class="max-w-4xl border-t border-slate-200 pt-4">
            <x-admin.delete-button :action="route('admin.materials.destroy', $material)" label="Hapus bahan" />
        </div>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.querySelector('[data-material-form]');
        var measure = form.querySelector('[data-measure]');
        function sync() {
            var pipe = measure.value === 'length';
            form.querySelectorAll('[data-pipe-only]').forEach(function (el) { el.classList.toggle('hidden', !pipe); });
            form.querySelectorAll('[data-unit-only]').forEach(function (el) { el.classList.toggle('hidden', pipe); });
            var price = form.querySelector('label[for="last_price"]');
            if (price) price.firstChild.textContent = pipe ? 'Harga beli per batang (Rp)' : 'Harga beli per satuan beli (Rp)';
            var min = form.querySelector('label[for="min_stock"]');
            if (min) min.firstChild.textContent = pipe ? 'Stok minimum (cm)' : 'Stok minimum (satuan pakai)';
        }
        measure.addEventListener('change', sync);

        var unit = form.querySelector('[data-unit]');
        var useUnit = form.querySelector('[data-use-unit]');
        var conversion = form.querySelector('[data-conversion]');
        var label = form.querySelector('[data-conversion-label]');
        var price = form.querySelector('#last_price');
        function syncConversion() {
            var has = useUnit.value && useUnit.value !== unit.value;
            conversion.disabled = !has;
            conversion.closest('div').classList.toggle('opacity-50', !has);
            var n = parseFloat(conversion.value) || 0;
            if (has && n > 0) {
                var per = (parseFloat(price.value) || 0) / n;
                label.textContent = '1 ' + unit.value + ' = ' + n.toLocaleString('id-ID') + ' ' + useUnit.value
                    + (per ? ' → harga per ' + useUnit.value + ' Rp ' + per.toLocaleString('id-ID', { maximumFractionDigits: 2 }) : '');
            } else {
                label.textContent = has ? 'Isi berapa ' + useUnit.value + ' dalam 1 ' + unit.value + '.' : 'Stok & formula memakai satuan ' + unit.value + '.';
            }
        }
        [unit, useUnit, conversion, price].forEach(function (el) { el.addEventListener('input', syncConversion); el.addEventListener('change', syncConversion); });
        syncConversion();
    })();
</script>
@endpush
