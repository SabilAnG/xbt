@extends('layouts.admin', ['title' => $item->exists ? 'Ubah produk' : 'Produk baru', 'subtitle' => $item->exists ? $item->name : 'Tambah knalpot jadi ke daftar Stok Produk'])

@section('content')
    <form method="POST" action="{{ $item->exists ? route('admin.items.update', $item) : route('admin.items.store') }}" class="card max-w-4xl space-y-6 p-6">
        @csrf
        @if ($item->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.field name="sku" label="SKU" :value="$item->sku ?? 'KNL-'.str_pad((string) (\App\Models\Item::max('id') + 1), 3, '0', STR_PAD_LEFT)" required />
            <x-admin.field name="model_motor" label="Model motor" :value="$item->model_motor" required list="model-list" class="sm:col-span-2" />
            <x-admin.field name="finishing" label="Finishing" :value="$item->finishing" list="finishing-list" />
            <x-admin.field name="variant" label="Tipe" :value="$item->variant" list="variant-list" />
            <x-admin.field name="size" label="Ukuran" :value="$item->size" list="size-list" />
            <x-admin.select name="brand" label="Merek" :value="$item->brand" :options="$options['merek']->mapWithKeys(fn ($b) => [$b => $b])" placeholder="— tanpa merek —"
                hint="Kelola di menu Merek." />
            <x-admin.field name="name" label="Nama produk" :value="$item->name" class="sm:col-span-2" hint="Kosongkan agar dibuat otomatis dari model + finishing + tipe + ukuran + merek." />
        </div>

        @foreach (['model-list' => 'model_motor', 'finishing-list' => 'finishing', 'variant-list' => 'tipe', 'size-list' => 'ukuran'] as $listId => $list)
            <datalist id="{{ $listId }}">
                @foreach ($options[$list] as $option)
                    <option value="{{ $option }}">
                @endforeach
            </datalist>
        @endforeach
        <p class="hint -mt-4">Pilihan diambil dari produk yang sudah ada; boleh juga mengetik nilai baru.</p>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <p class="label">Formula</p>
                <p class="py-2 text-sm text-slate-600">{{ $item->formula?->name ?? 'Produk dihubungkan dari menu Formula Knalpot.' }}</p>
            </div>
            <x-admin.field name="min_stock" label="Stok minimum" type="number" step="any" min="0" :value="(float) $item->min_stock" required />
            <x-admin.field name="opening_stock" label="Stok awal" type="number" step="any" :value="(float) $item->opening_stock" hint="Hasil hitung fisik sebelum pencatatan." />
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $item->is_active ?? true))> Aktif
                </label>
            </div>
            <x-admin.field name="cost_price" label="HPP / unit" type="number" step="any" min="0" :value="(float) $item->cost_price" required hint="Diisi otomatis dari produksi (rata-rata)." />
            <x-admin.field name="sell_price" label="Harga jual offline" type="number" step="any" min="0" :value="(float) $item->sell_price" required />
            <x-admin.field name="sell_price_online" label="Harga jual online" type="number" step="any" min="0" :value="(float) $item->sell_price_online" required />
        </div>

        <x-admin.field name="notes" label="Catatan" type="textarea" :value="$item->notes" />

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ $item->exists ? route('admin.items.show', $item) : route('admin.items.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
