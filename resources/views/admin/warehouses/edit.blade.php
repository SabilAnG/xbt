@extends('layouts.admin', ['title' => 'Ubah info gudang', 'subtitle' => 'Gudang '.$warehouse->typeLabel()])

@section('content')
    <form method="POST" action="{{ route('admin.warehouses.update', $warehouse->slug()) }}" class="card max-w-2xl space-y-6 p-6">
        @csrf
        @method('PUT')
        <div class="grid gap-4">
            <x-admin.field name="name" label="Nama gudang" :value="$warehouse->name" required />
            <x-admin.field name="address" label="Lokasi / alamat" :value="$warehouse->address" placeholder="mis. Bengkel belakang, rak A–C" />
            <x-admin.field name="description" label="Keterangan" :value="$warehouse->description" />
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.warehouses.show', $warehouse->slug()) }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
