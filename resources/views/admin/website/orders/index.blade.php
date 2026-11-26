@extends('layouts.admin', ['title' => 'Pesanan & Tracking'])

@section('actions')
    <a href="{{ route('tracking') }}" target="_blank" class="btn hidden sm:inline-flex">Halaman tracking ↗</a>
    <a href="{{ route('admin.orders.create') }}" class="btn btn-primary">+ Pesanan</a>
@endsection

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="No. pesanan, pembeli, atau no. resi">
        </div>
        <x-admin.select name="status" label="Status" :options="$statuses" :value="request('status')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>No. pesanan</th><th>Pembeli</th><th>Kontak</th><th>Status</th><th class="num">Pengiriman</th><th>Dibuat</th><th></th></tr></thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.edit', $order) }}" class="link font-mono text-xs">{{ $order->order_number }}</a></td>
                        <td>{{ $order->customer_name ?? '—' }}</td>
                        <td class="text-xs">{{ collect([$order->customer_phone, $order->customer_email])->filter()->implode(' · ') ?: '—' }}</td>
                        <td><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium">{{ $statuses[$order->status] ?? $order->status }}</span></td>
                        <td class="num">{{ $order->shipments_count }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $order->created_at?->translatedFormat('d M Y') }}</td>
                        <td class="text-right"><a href="{{ route('admin.orders.edit', $order) }}" class="btn btn-sm">Kelola</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">Belum ada pesanan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $orders->links() }}
@endsection
