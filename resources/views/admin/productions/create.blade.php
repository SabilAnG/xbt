@extends('layouts.admin', ['title' => 'Input produksi', 'subtitle' => 'Bahan dipotong sesuai resep produk; stok bahan boleh minus (tanda ada pembelian yang belum dicatat)'])

@section('content')
    <form method="POST" action="{{ route('admin.productions.store') }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.field name="produced_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
            <x-admin.select name="item_id" label="Produk" required class="sm:col-span-2"
                :options="$items->mapWithKeys(fn ($item) => [$item->id => $item->name.($item->formula ? '' : ' (belum ada resep)')])" />
            <x-admin.field name="qty" label="Qty jadi" type="number" step="any" min="0" required />
            <x-admin.field name="worker" label="Tukang" class="sm:col-span-2" />
        </div>
        <x-admin.field name="notes" label="Catatan" type="textarea" />
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.productions.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan produksi</button>
        </div>
    </form>
@endsection
