@extends('layouts.admin', ['title' => 'Input penjualan', 'subtitle' => 'Pilih produk, qty, harga deal. Kalau uang belum lunas, isi uang masuk sebagian.'])

@section('content')
    <form method="POST" action="{{ route('admin.sales.store') }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.field name="sold_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
            <x-admin.select name="item_id" label="Produk" required class="sm:col-span-2"
                :options="$items->mapWithKeys(fn ($item) => [$item->id => $item->name.' — stok '.qty($item->stock)])" />
            <x-admin.field name="qty" label="Qty" type="number" step="any" required hint="Isi minus untuk retur." />
            <x-admin.field name="unit_price" label="Harga satuan (deal)" type="number" step="any" min="0" required
                hint="Harga daftar: offline untuk saluran Offline, selain itu harga online." />
            <x-admin.select name="channel" label="Saluran" :options="collect($channels)->mapWithKeys(fn ($c) => [$c => $c])" />
            <x-admin.field name="customer_name" label="Pembeli" class="sm:col-span-2" />
            <x-admin.field name="paid_amount" label="Uang masuk" type="number" step="any" min="0" hint="Kosongkan bila belum dibayar." />
        </div>
        <x-admin.field name="notes" label="Keterangan" type="textarea" />

        <div class="card bg-slate-50 p-4 text-xs text-slate-600 shadow-none">
            <p class="mb-2 font-semibold text-slate-700">Harga daftar</p>
            <div class="max-h-40 space-y-1 overflow-y-auto">
                @foreach ($items->unique(fn ($item) => $item->sell_price.'|'.$item->sell_price_online.'|'.$item->formula_id) as $item)
                    <p>{{ $item->model_motor }} {{ $item->finishing }}: offline {{ rupiah($item->sell_price) }} · online {{ rupiah($item->sell_price_online) }}</p>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.sales.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan penjualan</button>
        </div>
    </form>
@endsection
