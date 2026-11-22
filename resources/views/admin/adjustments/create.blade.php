@extends('layouts.admin', ['title' => 'Penyesuaian stok bahan', 'subtitle' => 'Isi plus bila ada temuan, minus bila rusak/hilang'])

@section('content')
    <form method="POST" action="{{ route('admin.adjustments.store') }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.field name="moved_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
            <x-admin.select name="material_id" label="Bahan" required class="sm:col-span-2" :value="request('material')"
                :options="$materials->mapWithKeys(fn ($m) => [$m->id => $m->name.' — sisa '.qty($m->stock).' '.$m->unit])" />
            <x-admin.field name="qty" label="Qty (+/−)" type="number" step="any" required />
            <x-admin.field name="reason" label="Alasan" required class="sm:col-span-2" />
        </div>
        <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4">
            <a href="{{ route('admin.adjustments.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
