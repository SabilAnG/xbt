@extends('layouts.admin', ['title' => $material->exists ? 'Ubah bahan' : 'Bahan baru', 'subtitle' => $material->exists ? $material->name : 'Tambah bahan baku ke daftar Stok Bahan'])

@section('content')
    <form method="POST" action="{{ $material->exists ? route('admin.materials.update', $material) : route('admin.materials.store') }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        @if ($material->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.field name="sku" label="SKU" :value="$material->sku ?? 'BHN-'.str_pad((string) (\App\Models\Material::max('id') + 1), 3, '0', STR_PAD_LEFT)" required />
            <x-admin.field name="name" label="Nama bahan" :value="$material->name" required class="sm:col-span-2" />
            <x-admin.field name="category" label="Kategori" :value="$material->category?->name" required list="category-list" hint="Pilih atau ketik kategori baru." />
            <x-admin.field name="unit" label="Satuan" :value="$material->unit" required list="unit-list" />
            <x-admin.field name="min_stock" label="Stok minimum" type="number" step="any" min="0" :value="(float) $material->min_stock" required />
            <x-admin.field name="opening_stock" label="Stok awal (hitung fisik)" type="number" step="any" :value="(float) $material->opening_stock" />
            <x-admin.field name="cost_price" label="Harga rata-rata" type="number" step="any" min="0" :value="(float) $material->cost_price" hint="Diperbarui otomatis setiap ada pembelian." />
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $material->is_active ?? true))> Aktif
                </label>
            </div>
        </div>

        <datalist id="category-list">
            @foreach ($categories as $category)
                <option value="{{ $category->name }}">
            @endforeach
        </datalist>
        <datalist id="unit-list">
            @foreach (\App\Models\Material::query()->distinct()->orderBy('unit')->pluck('unit') as $unit)
                <option value="{{ $unit }}">
            @endforeach
        </datalist>

        <x-admin.field name="notes" label="Catatan" type="textarea" :value="$material->notes" />

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ $material->exists ? route('admin.materials.show', $material) : route('admin.materials.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
