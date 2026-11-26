@extends('layouts.admin', ['title' => $supplier->name, 'subtitle' => collect([$supplier->phone, $supplier->address])->filter()->implode(' · ') ?: 'Supplier'])

@section('actions')
    <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary">+ Pembelian</a>
@endsection

@section('content')
    <section class="stat-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Total pembelian" :value="rupiah($total)" />
        <x-admin.stat label="Hutang" :value="rupiah($payable)" :tone="$payable > 0 ? 'danger' : 'success'" />
        <x-admin.stat label="Menunggu diterima" :value="$awaiting.' pembelian'" :tone="$awaiting > 0 ? 'warning' : 'success'" />
        <x-admin.stat label="Pekerjaan vendor" :value="$vendorJobs.' produksi'" />
    </section>

    <div class="grid gap-4 sm:gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Pembelian</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Nomor</th><th>Tanggal</th><th>Barang</th><th class="num">Total</th><th class="num">Sisa hutang</th><th>Barang</th></tr></thead>
                    <tbody>
                    @forelse ($purchases as $purchase)
                        <tr>
                            <td class="font-mono text-xs whitespace-nowrap"><a href="{{ route('admin.purchases.show', $purchase) }}" class="link">{{ $purchase->invoice_number }}</a></td>
                            <td class="whitespace-nowrap">{{ $purchase->purchased_at->translatedFormat('d M Y') }}</td>
                            <td class="min-w-48 text-xs text-slate-600">{{ $purchase->isService() ? 'Jasa vendor · '.$purchase->notes : $purchase->items->map(fn ($line) => $line->material->name.' × '.qty($line->qty))->implode(' · ') }}</td>
                            <td class="num">{{ rupiah($purchase->total) }}</td>
                            <td class="num {{ $purchase->payable() > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ $purchase->payable() > 0 ? rupiah($purchase->payable()) : '—' }}</td>
                            <td>@if ($purchase->receiveStatus())<x-admin.status :value="$purchase->receiveStatus()" />@else<span class="text-xs text-slate-400">jasa</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-500">Belum ada pembelian dari supplier ini.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($purchases->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $purchases->links() }}</div>@endif
        </section>

        <div class="space-y-4 self-start">
            <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}" class="card space-y-4 p-6">
                @csrf
                @method('PUT')
                <h2 class="card-title">Data supplier</h2>
                <x-admin.field name="name" label="Nama" :value="$supplier->name" required />
                <x-admin.field name="phone" label="Telepon / WA" :value="$supplier->phone" />
                <x-admin.field name="address" label="Alamat" :value="$supplier->address" />
                <x-admin.field name="notes" label="Catatan" type="textarea" :value="$supplier->notes" />
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($supplier->is_active)> Aktif (muncul di pilihan pembelian)</label>
                <div class="flex justify-end"><button class="btn btn-primary">Simpan</button></div>
            </form>
            @if ($purchases->total() === 0)
                <x-admin.delete-button :action="route('admin.suppliers.destroy', $supplier)" label="Hapus supplier" :confirm="'Hapus supplier '.$supplier->name.'?'" />
            @endif
        </div>
    </div>
@endsection
