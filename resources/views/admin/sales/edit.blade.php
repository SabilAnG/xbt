@extends('layouts.admin', ['title' => 'Ubah penjualan '.$sale->invoice_number])

@section('content')
    <form method="POST" action="{{ route('admin.sales.update', $sale) }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        @method('PUT')
        <div class="rounded-lg bg-slate-50 p-4 text-sm">
            <p class="font-semibold text-slate-900">{{ $sale->itemSummary(10) }}</p>
            <p class="text-slate-600">{{ $sale->sold_at->translatedFormat('d F Y') }} · total <strong>{{ rupiah($sale->total) }}</strong> · dibayar {{ rupiah($sale->paid_amount) }}</p>
            <p class="mt-1 text-[12px] text-muted">Produk & harga tidak bisa diubah (hapus nota lalu input ulang). Uang masuk dicatat di halaman nota.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.select name="channel" label="Saluran" :options="collect($channels)->mapWithKeys(fn ($c) => [$c => $c])" :value="$sale->channel" />
            <x-admin.field name="customer_name" label="Pembeli" :value="$sale->customer_name" />
            <x-admin.field name="customer_phone" label="Telepon" :value="$sale->customer_phone" />
        </div>
        <x-admin.field name="notes" label="Keterangan" type="textarea" :value="$sale->notes" />
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.sales.show', $sale) }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
