@extends('layouts.admin', ['title' => 'Ubah penjualan '.$sale->invoice_number, 'subtitle' => 'Untuk mengubah produk/qty/harga, hapus penjualan lalu input ulang.'])

@section('content')
    @php $line = $sale->items->first(); @endphp
    <form method="POST" action="{{ route('admin.sales.update', $sale) }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        @method('PUT')
        <div class="rounded-lg bg-zinc-50 p-4 text-sm">
            <p class="font-semibold text-zinc-900">{{ $line?->item?->name }}</p>
            <p class="text-zinc-600">{{ $sale->sold_at->translatedFormat('d F Y') }} · {{ qty($line?->qty) }} × {{ rupiah($line?->unit_price) }} = <strong>{{ rupiah($sale->total) }}</strong></p>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.select name="channel" label="Saluran" :options="collect($channels)->mapWithKeys(fn ($c) => [$c => $c])" :value="$sale->channel" />
            <x-admin.field name="customer_name" label="Pembeli" :value="$sale->customer_name" />
            <x-admin.field name="paid_amount" label="Uang masuk" type="number" step="any" min="0" :value="(float) $sale->paid_amount" required />
        </div>
        <x-admin.field name="notes" label="Keterangan" type="textarea" :value="$sale->notes" />
        <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4">
            <a href="{{ route('admin.sales.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
