@extends('layouts.admin', ['title' => 'Input pembelian bahan', 'subtitle' => 'Jangan dicatat dua kali: pilih Pembelian Bahan ATAU Pengeluaran untuk belanja yang sama'])

@section('content')
    <form method="POST" action="{{ route('admin.purchases.store') }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.field name="purchased_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
            <x-admin.select name="material_id" label="Bahan" required class="sm:col-span-2" :value="request('material')"
                :options="$materials->mapWithKeys(fn ($m) => [$m->id => $m->name.' ('.$m->unit.')'])" />
            <x-admin.field name="qty" label="Qty" type="number" step="any" min="0" required />
            <x-admin.field name="unit_price" label="Harga satuan" type="number" step="any" min="0" required />
            <x-admin.field name="supplier" label="Toko / supplier" />
            <x-admin.field name="paid_from" label="Dibayar dari" class="sm:col-span-2" />
        </div>
        <label class="flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" name="recorded_in_expenses" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('recorded_in_expenses'))>
            <span>Uangnya sudah dicatat di Pengeluaran <span class="block text-xs text-slate-500">Centang agar tidak dihitung dobel di dashboard. Belanja baru: biarkan kosong.</span></span>
        </label>
        <x-admin.field name="notes" label="Catatan" type="textarea" />
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.purchases.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan pembelian</button>
        </div>
    </form>
@endsection
