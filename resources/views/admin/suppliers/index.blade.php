@extends('layouts.admin', ['title' => 'Supplier'])

@section('content')
    <div class="grid gap-4 sm:gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-4">
            <x-admin.filters>
                <div class="min-w-56 flex-1">
                    <label class="label" for="q">Cari</label>
                    <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama, telepon, atau alamat">
                </div>
            </x-admin.filters>

            <section class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Supplier</th><th>Kontak</th><th class="num">Pembelian</th><th class="num">Total</th><th class="num">Hutang</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($suppliers as $supplier)
                            @php $payable = max((float) $supplier->purchases_total - (float) $supplier->purchases_paid, 0); @endphp
                            <tr>
                                <td><a href="{{ route('admin.suppliers.show', $supplier) }}" class="link font-medium">{{ $supplier->name }}</a></td>
                                <td class="text-xs text-slate-600">{{ collect([$supplier->phone, $supplier->address])->filter()->implode(' · ') ?: '—' }}</td>
                                <td class="num">{{ $supplier->purchases_count }}</td>
                                <td class="num">{{ rupiah($supplier->purchases_total ?? 0) }}</td>
                                <td class="num {{ $payable > 0 ? 'font-semibold text-rose-600' : 'text-slate-400' }}">{{ $payable > 0 ? rupiah($payable) : '—' }}</td>
                                <td><x-admin.status :value="$supplier->is_active ? 'AKTIF' : 'NONAKTIF'" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada supplier.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <form method="POST" action="{{ route('admin.suppliers.store') }}" class="card space-y-4 self-start p-6">
            @csrf
            <h2 class="card-title">Tambah supplier</h2>
            <x-admin.field name="name" label="Nama" required placeholder="mis. Toko Besi Jaya" />
            <x-admin.field name="phone" label="Telepon / WA" />
            <x-admin.field name="address" label="Alamat" />
            <x-admin.field name="notes" label="Catatan" type="textarea" placeholder="mis. barang yang biasa dibeli, jasa yang dikerjakan" />
            <div class="flex justify-end"><button class="btn btn-primary">Tambah</button></div>
        </form>
    </div>
@endsection
