@extends('layouts.admin', ['title' => $brand->exists ? 'Ubah merek '.$brand->name : 'Merek baru'])

@section('content')
    <form method="POST" action="{{ $brand->exists ? route('admin.brands.update', $brand) : route('admin.brands.store') }}" class="card max-w-2xl space-y-6 p-6">
        @csrf
        @if ($brand->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.field name="name" label="Nama merek" :value="$brand->name" required class="sm:col-span-2" placeholder="mis. XBT" />
            <x-admin.field name="code" label="Kode singkat" :value="$brand->code" hint="Tampil di label, maks 4 huruf terlihat." />
            <div>
                <label class="label" for="color">Warna label</label>
                <input id="color" name="color" type="color" value="{{ old('color', $brand->color) }}" class="input h-9.5 p-1">
                @error('color')<p class="error">{{ $message }}</p>@enderror
            </div>
            <x-admin.field name="sort_order" label="Urutan" type="number" min="0" :value="$brand->sort_order" />
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active))> Aktif
                </label>
            </div>
            <x-admin.field name="description" label="Keterangan" :value="$brand->description" class="sm:col-span-3" placeholder="mis. Lini premium stainless" />
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.brands.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
