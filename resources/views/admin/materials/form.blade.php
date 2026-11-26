@extends('layouts.admin', ['title' => $material->exists ? 'Ubah item' : 'Item baru', 'subtitle' => 'Pipa dicatat per batang dan dipotong per cm. Bahan lain bisa punya satuan beli & satuan pakai berbeda (mis. plat: beli per lembar, pakai per cm²). Harga beli dipakai formula selama belum ada stok.'])

@php
    $shape = old('shape', $material->measure === \App\Models\Material::LENGTH ? 'pipa' : (string) $material->shape);
    $isPipe = $shape === 'pipa';
    $isPlate = $shape === 'plat';
@endphp

@section('content')
    <form method="POST" action="{{ $material->exists ? route('admin.materials.update', $material) : route('admin.materials.store') }}" class="max-w-4xl space-y-6" data-material-form>
        @csrf
        @if ($material->exists) @method('PUT') @endif

        <section class="card space-y-4 p-6">
            <h2 class="card-title">Data bahan</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.select name="shape" label="Bentuk" :options="\App\Models\Material::SHAPES" :value="$shape" :placeholder="false" data-shape
                    hint="Pipa dihitung per batang & cm; plat dan lainnya per satuan." />
                <x-admin.select name="warehouse_id" label="Gudang asal" :options="$warehouses" :value="$material->warehouse_id" :placeholder="false" required
                    hint="Bahan Baku = mentah; Setengah Jadi = beli jadi / hasil proses; Aksesoris = baut, stiker, dus." />
                <x-admin.field name="code" label="Kode" :value="$material->code" placeholder="Kosongkan = otomatis" />
                <x-admin.field name="name" label="Nama bahan" :value="$material->name" required class="sm:col-span-2" placeholder="mis. Pipa Stainless 201 / Pureng Mio" />
                <div>
                    <label for="category" class="label">Kategori</label>
                    <input id="category" name="category" value="{{ old('category', $material->category?->name) }}" class="input" list="material-categories" placeholder="mis. Pipa, Plendes">
                    <datalist id="material-categories">@foreach ($categories as $category)<option value="{{ $category }}">@endforeach</datalist>
                </div>
                <div data-other-only @class(['sm:col-span-2', 'hidden' => $isPipe || $isPlate])>
                    <x-admin.field name="spec" label="Spesifikasi" :value="$material->shape ? null : $material->spec" placeholder="mis. M8 × 20 mm, warna hitam" />
                </div>
            </div>
        </section>

        <section data-plate-only @class(['card space-y-4 p-6', 'hidden' => ! $isPlate])>
            <h2 class="card-title">Ukuran plat</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.field name="sheet_length_cm" label="Panjang lembar (cm)" type="number" step="any" min="0" :value="$material->sheet_length_cm ? (float) $material->sheet_length_cm : null" placeholder="mis. 240" data-sheet />
                <x-admin.field name="sheet_width_cm" label="Lebar lembar (cm)" type="number" step="any" min="0" :value="$material->sheet_width_cm ? (float) $material->sheet_width_cm : null" placeholder="mis. 120" data-sheet />
                <div>
                    <p class="label">Luas 1 lembar</p>
                    <p class="py-2 text-sm font-semibold" data-sheet-area>—</p>
                </div>
            </div>
            <p class="text-xs text-slate-500">Bila satuan pakai cm², isi per lembar otomatis = panjang × lebar.</p>
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
            <h2 class="card-title">Ukuran pipa</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.field name="diameter" label="Diameter" :value="$material->diameter" placeholder="mis. 1¼&quot; atau 32 mm" />
                <x-admin.field name="thickness_mm" label="Tebal (mm)" type="number" step="any" min="0" :value="$material->thickness_mm ? (float) $material->thickness_mm : null" placeholder="mis. 1,2" />
                <x-admin.field name="bar_length_cm" label="Panjang 1 batang (cm)" type="number" step="any" min="0" :value="$material->bar_length_cm ? (float) $material->bar_length_cm : null"
                    hint="Panjang standar saat dibeli, mis. 600 cm." />
                <x-admin.field name="min_reusable_cm" label="Sisa minimal dipakai lagi (cm)" type="number" step="any" min="0" :value="(float) $material->min_reusable_cm"
                    hint="Sisa potong lebih pendek dari ini = afkir, tidak masuk Stok Sisa." />
            </div>
        </section>

        <section class="card space-y-4 p-6">
            <h2 class="card-title">Asal barang</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_purchasable" value="0">
                    <input type="checkbox" name="is_purchasable" value="1" class="mt-0.5" @checked(old('is_purchasable', $material->is_purchasable ?? true))>
                    <span><span class="font-medium">Bisa dibeli</span><span class="block text-xs text-slate-500">Muncul di form pembelian.</span></span>
                </label>
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_producible" value="0">
                    <input type="checkbox" name="is_producible" value="1" class="mt-0.5" @checked(old('is_producible', $material->is_producible ?? true))>
                    <span><span class="font-medium">Bisa diproduksi</span><span class="block text-xs text-slate-500">Bisa jadi hasil formula setengah jadi.</span></span>
                </label>
                <x-admin.select name="preferred_source" label="Sumber utama (bila bisa keduanya)" :options="\App\Models\Material::SOURCES" :value="$material->preferred_source ?? 'beli'" :placeholder="false"
                    hint="Saran awal di Rencana Produksi; bisa dipecah per rencana." />
            </div>
            @error('is_purchasable')<p class="error">{{ $message }}</p>@enderror
            @error('is_producible')<p class="error">{{ $message }}</p>@enderror
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
        var shape = form.querySelector('[data-shape]');
        function sync() {
            var pipe = shape.value === 'pipa';
            var plate = shape.value === 'plat';
            form.querySelectorAll('[data-pipe-only]').forEach(function (el) { el.classList.toggle('hidden', !pipe); });
            form.querySelectorAll('[data-unit-only]').forEach(function (el) { el.classList.toggle('hidden', pipe); });
            form.querySelectorAll('[data-plate-only]').forEach(function (el) { el.classList.toggle('hidden', !plate); });
            form.querySelectorAll('[data-other-only]').forEach(function (el) { el.classList.toggle('hidden', pipe || plate); });
            var price = form.querySelector('label[for="last_price"]');
            if (price) price.firstChild.textContent = pipe ? 'Harga beli per batang (Rp)' : 'Harga beli per satuan beli (Rp)';
            var min = form.querySelector('label[for="min_stock"]');
            if (min) min.firstChild.textContent = pipe ? 'Stok minimum (cm)' : 'Stok minimum (satuan pakai)';
        }
        shape.addEventListener('change', function () { sync(); syncSheet(); });

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
        function syncSheet() {
            var l = parseFloat(form.querySelector('#sheet_length_cm').value) || 0;
            var w = parseFloat(form.querySelector('#sheet_width_cm').value) || 0;
            form.querySelector('[data-sheet-area]').textContent = l && w ? (l * w).toLocaleString('id-ID') + ' cm²' : '—';
            if (shape.value === 'plat' && l && w && useUnit.value === 'cm²') { conversion.value = l * w; syncConversion(); }
        }
        form.querySelectorAll('[data-sheet]').forEach(function (el) { el.addEventListener('input', syncSheet); });
        useUnit.addEventListener('change', syncSheet);
        [unit, useUnit, conversion, price].forEach(function (el) { el.addEventListener('input', syncConversion); el.addEventListener('change', syncConversion); });
        syncConversion();
        syncSheet();
    })();
</script>
@endpush
