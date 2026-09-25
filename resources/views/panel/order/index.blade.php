<x-panel.layout judul="Orders" keterangan="Order pembeli. Nomor resi di sini yang dibaca pembeli di halaman lacak.">

    <x-slot:aksi>
        <a href="{{ route('panel.order.create') }}" class="tombol tombol-utama">
            <x-panel.ikon nama="tambah" kelas="h-4 w-4" />
            Tambah Order
        </a>
    </x-slot:aksi>

    <form method="GET" class="kartu mb-4 flex flex-wrap items-center gap-3 p-3">
        <label class="relative min-w-0 flex-1 basis-56">
            <span class="sr-only">Cari order</span>
            <x-panel.ikon nama="cari" kelas="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="cari" value="{{ $cari }}" data-cari
                   placeholder="Cari nomor order, nama, atau telepon…" class="isian pl-10">
        </label>

        <select name="status" onchange="this.form.submit()" class="isian w-auto min-w-44">
            <option value="">Semua status</option>
            @foreach (App\Models\Order::STATUSES as $nilai => $label)
                <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
            @endforeach
        </select>

        <p class="ml-auto shrink-0 px-1 text-[0.8125rem] text-slate-500">
            {{ number_format($order->total(), 0, ',', '.') }} order
        </p>
    </form>

    <div class="kartu overflow-hidden">
        <div class="max-h-[calc(100dvh-16rem)] overflow-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        <th class="th">Order</th>
                        <th class="th">Pembeli</th>
                        <th class="th text-right">Pengiriman</th>
                        <th class="th">Status</th>
                        <th class="th w-px text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($order as $o)
                        <tr class="transition hover:bg-ungu-500/5">
                            <td class="td">
                                <p class="font-medium text-slate-800">{{ $o->order_number }}</p>
                                <p class="text-[0.75rem] text-slate-400">{{ $o->created_at?->format('d/m/Y') }}</p>
                            </td>
                            <td class="td">
                                <p class="text-slate-700">{{ $o->customer_name ?: '—' }}</p>
                                <p class="text-[0.75rem] text-slate-400">{{ $o->customer_phone ?: $o->customer_email }}</p>
                            </td>
                            <td class="td text-right text-slate-600">{{ $o->shipments_count }}</td>
                            <td class="td">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[0.75rem] font-medium',
                                    'bg-mint-500/15 text-emerald-700' => $o->status === 'delivered',
                                    'bg-ungu-500/10 text-ungu-700' => in_array($o->status, ['shipped', 'in_transit'], true),
                                    'bg-merah-500/10 text-merah-500' => $o->status === 'cancelled',
                                    'bg-kuning-400/25 text-amber-700' => $o->status === 'processing',
                                ])>{{ App\Models\Order::STATUSES[$o->status] ?? $o->status }}</span>
                            </td>
                            <td class="td text-right">
                                <a href="{{ route('panel.order.edit', $o) }}"
                                   class="rounded-lg px-2.5 py-1 text-[0.8125rem] font-medium text-ungu-600 transition hover:bg-ungu-500/10">Ubah</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center">
                                <p class="text-sm font-medium text-slate-600">
                                    {{ $cari !== '' || $status ? 'Tidak ada yang cocok' : 'Belum ada order' }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($order->hasPages())
            <div class="border-t border-slate-100 px-4 py-2.5 text-[0.8125rem]">
                {{ $order->links('panel.partials.paginasi') }}
            </div>
        @endif
    </div>
</x-panel.layout>
