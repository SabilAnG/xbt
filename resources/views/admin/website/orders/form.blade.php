@extends('layouts.admin', ['title' => $order->exists ? 'Pesanan '.$order->order_number : 'Pesanan baru', 'subtitle' => 'Pelanggan memasukkan nomor pesanan di halaman Tracking'])

@section('actions')
    <a href="{{ route('admin.orders.index') }}" class="btn">Kembali</a>
@endsection

@section('content')
    <form method="POST" action="{{ $order->exists ? route('admin.orders.update', $order) : route('admin.orders.store') }}" class="card max-w-4xl space-y-6 p-6">
        @csrf
        @if ($order->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.field name="order_number" label="No. pesanan" :value="$order->order_number" required hint="Yang diketik pelanggan di form tracking." />
            <x-admin.select name="status" label="Status" :options="$statuses" :value="$order->status" :placeholder="false" required />
            <x-admin.field name="customer_name" label="Nama pembeli" :value="$order->customer_name" />
            <x-admin.field name="customer_phone" label="Telepon" :value="$order->customer_phone" />
            <x-admin.field name="customer_email" label="Email" type="email" :value="$order->customer_email" />
        </div>
        <x-admin.field name="notes" label="Catatan internal" type="textarea" :value="$order->notes" hint="Tidak pernah tampil di halaman tracking." />
        <div class="flex items-center justify-between gap-2 border-t border-slate-100 pt-4">
            <div>
                @if ($order->exists)
                    <button form="delete-order" class="btn btn-danger">Hapus pesanan</button>
                @endif
            </div>
            <button class="btn btn-primary">{{ $order->exists ? 'Simpan pesanan' : 'Buat pesanan' }}</button>
        </div>
    </form>

    @if ($order->exists)
        <form id="delete-order" method="POST" action="{{ route('admin.orders.destroy', $order) }}" onsubmit="return confirm('Hapus pesanan beserta semua pengirimannya?')">
            @csrf
            @method('DELETE')
        </form>

        <div class="max-w-4xl space-y-4">
            <h2 class="text-sm font-semibold text-slate-900">Pengiriman <span class="font-normal text-slate-500">— tiap pengiriman jadi satu tab di halaman tracking</span></h2>

            @foreach ($order->shipments as $shipment)
                <section class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ $shipment->provider ?: 'Kurir' }} · <span class="font-mono">{{ $shipment->tracking_number }}</span></h3>
                        <x-admin.delete-button :action="route('admin.shipments.destroy', $shipment)" label="Hapus pengiriman" confirm="Hapus pengiriman ini beserta riwayatnya?" />
                    </div>

                    <form method="POST" action="{{ route('admin.shipments.update', $shipment) }}" class="grid gap-4 border-b border-slate-100 p-5 sm:grid-cols-3">
                        @csrf
                        @method('PUT')
                        <div><label class="label">No. resi</label><input name="tracking_number" value="{{ $shipment->tracking_number }}" class="input" required></div>
                        <div><label class="label">Kurir</label><input name="provider" value="{{ $shipment->provider }}" class="input" placeholder="JNE, J&T, DHL…"></div>
                        <div><label class="label">Status</label><input name="status" value="{{ $shipment->status }}" class="input" required></div>
                        <div class="sm:col-span-3"><label class="label">Keterangan status</label><input name="status_description" value="{{ $shipment->status_description }}" class="input"></div>
                        <div><label class="label">Status diperbarui</label><input type="datetime-local" name="status_updated_at" value="{{ $shipment->status_updated_at?->format('Y-m-d\TH:i') }}" class="input"></div>
                        <div><label class="label">Terakhir dicek</label><input type="datetime-local" name="last_checked" value="{{ $shipment->last_checked?->format('Y-m-d\TH:i') }}" class="input"></div>
                        <div class="flex items-end justify-end"><button class="btn">Simpan pengiriman</button></div>
                    </form>

                    <div class="p-5">
                        <p class="mb-3 text-xs font-semibold tracking-wide text-slate-500 uppercase">Riwayat tracking (terbaru di atas · 5 riwayat = progress penuh)</p>
                        <ol class="space-y-2">
                            @forelse ($shipment->events as $event)
                                <li class="flex items-start gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                    <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $loop->first ? 'bg-brand-500' : 'bg-slate-300' }}"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium">{{ $event->status }} @if ($event->location)<span class="font-normal text-slate-500">· {{ $event->location }}</span>@endif</p>
                                        <p class="text-xs text-slate-500">{{ $event->happened_at?->translatedFormat('d M Y H:i') }} @if ($event->description) — {{ $event->description }} @endif</p>
                                    </div>
                                    <x-admin.delete-button :action="route('admin.shipment-events.destroy', $event)" label="×" confirm="Hapus riwayat ini?" />
                                </li>
                            @empty
                                <li class="text-sm text-slate-500">Belum ada riwayat.</li>
                            @endforelse
                        </ol>

                        <form method="POST" action="{{ route('admin.shipments.events.store', $shipment) }}" class="mt-4 grid gap-3 rounded-lg border border-dashed border-slate-300 p-4 sm:grid-cols-4">
                            @csrf
                            <div><label class="label">Status</label><input name="status" class="input" required placeholder="Diterima kurir"></div>
                            <div><label class="label">Waktu</label><input type="datetime-local" name="happened_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="input" required></div>
                            <div><label class="label">Lokasi</label><input name="location" class="input" placeholder="Jakarta"></div>
                            <div><label class="label">Keterangan</label><input name="description" class="input"></div>
                            <div class="flex justify-end sm:col-span-4"><button class="btn btn-sm">+ Tambah riwayat</button></div>
                        </form>
                    </div>
                </section>
            @endforeach

            <form method="POST" action="{{ route('admin.orders.shipments.store', $order) }}" class="card grid gap-4 p-5 sm:grid-cols-4">
                @csrf
                <p class="text-sm font-semibold text-slate-900 sm:col-span-4">Tambah pengiriman</p>
                <div><label class="label">No. resi *</label><input name="tracking_number" class="input" required></div>
                <div><label class="label">Kurir</label><input name="provider" class="input" placeholder="JNE, J&T, DHL…"></div>
                <div><label class="label">Status *</label><input name="status" value="in_transit" class="input" required></div>
                <div><label class="label">Keterangan</label><input name="status_description" class="input"></div>
                @if ($errors->has('tracking_number') || $errors->has('status'))
                    <p class="error sm:col-span-4">{{ $errors->first('tracking_number') ?: $errors->first('status') }}</p>
                @endif
                <div class="flex justify-end sm:col-span-4"><button class="btn btn-primary">+ Tambah pengiriman</button></div>
            </form>
        </div>
    @endif
@endsection
