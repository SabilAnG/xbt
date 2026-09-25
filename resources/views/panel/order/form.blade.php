@php $baru = ! $order->exists; @endphp

<x-panel.layout :judul="$baru ? 'Tambah Order' : $order->order_number"
                :keterangan="$baru ? null : ($order->customer_name ?: null)">

    <x-slot:aksi>
        <a href="{{ route('panel.order.index') }}" class="tombol tombol-hening">Batal</a>
        <button type="submit" form="form-order" class="tombol tombol-utama">Simpan</button>
    </x-slot:aksi>

    <div class="grid gap-4 lg:grid-cols-3">
        <form id="form-order" method="POST" class="space-y-4 lg:col-span-2"
              action="{{ $baru ? route('panel.order.store') : route('panel.order.update', $order) }}">
            @csrf
            @unless ($baru) @method('PUT') @endunless

            <section class="kartu grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label for="order_number" class="label">Nomor Order</label>
                    <input id="order_number" name="order_number" class="isian" required maxlength="64"
                           value="{{ old('order_number', $order->order_number) }}">
                    <p class="mt-1 text-[0.75rem] text-slate-400">Ini yang diketik pembeli di halaman lacak.</p>
                    @error('order_number') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="status" class="label">Status</label>
                    <select id="status" name="status" class="isian" required>
                        @foreach (App\Models\Order::STATUSES as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('status', $order->status ?? 'processing') === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="customer_name" class="label">Nama Pembeli</label>
                    <input id="customer_name" name="customer_name" class="isian" maxlength="255"
                           value="{{ old('customer_name', $order->customer_name) }}">
                </div>

                <div>
                    <label for="customer_phone" class="label">Telepon</label>
                    <input id="customer_phone" name="customer_phone" class="isian" maxlength="64"
                           value="{{ old('customer_phone', $order->customer_phone) }}">
                </div>

                <div class="sm:col-span-2">
                    <label for="customer_email" class="label">Email</label>
                    <input id="customer_email" name="customer_email" type="email" class="isian" maxlength="255"
                           value="{{ old('customer_email', $order->customer_email) }}">
                    @error('customer_email') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="notes" class="label">Catatan</label>
                    <textarea id="notes" name="notes" rows="3" class="isian">{{ old('notes', $order->notes) }}</textarea>
                </div>
            </section>
        </form>

        {{-- Pengiriman: form sendiri supaya menambah resi tidak ikut menyimpan
             isian order di kiri yang mungkin belum selesai diketik. --}}
        <section class="kartu h-fit p-5">
            <div class="mb-1 flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-800">Pengiriman</h2>

                @unless ($baru)
                    <form method="POST" action="{{ route('panel.order.segarkan-dhl', $order) }}">
                        @csrf
                        <button class="rounded-full px-2.5 py-1 text-[0.75rem] font-semibold text-ungu-600 transition hover:bg-ungu-500/10">
                            Tarik DHL
                        </button>
                    </form>
                @endunless
            </div>

            @if ($baru)
                <p class="text-[0.8125rem] text-slate-500">Simpan ordernya dulu, lalu resi bisa ditambahkan di sini.</p>
            @else
                <p class="mb-4 text-[0.8125rem] text-slate-500">
                    Kurir yang memuat kata "dhl" disegarkan otomatis dari MyDHL saat pembeli membuka halaman lacak.
                </p>

                <form method="POST" action="{{ route('panel.order.tambah-kiriman', $order) }}" class="space-y-2">
                    @csrf
                    <input name="provider" class="isian" required placeholder="Kurir — mis. DHL Express" value="{{ old('provider') }}">
                    <input name="tracking_number" class="isian" required placeholder="Nomor resi" value="{{ old('tracking_number') }}">
                    @error('provider') <p class="text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                    @error('tracking_number') <p class="text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                    <button class="tombol tombol-utama w-full">Tambah resi</button>
                </form>

                <div class="mt-4 space-y-2">
                    @forelse ($order->shipments as $kiriman)
                        <div class="kartu-kecil p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-[0.8125rem] font-semibold text-slate-800">{{ $kiriman->tracking_number }}</p>
                                    <p class="truncate text-[0.75rem] text-slate-400">
                                        {{ $kiriman->provider }}
                                        @if ($kiriman->pakaiDhl()) &middot; <span class="text-ungu-600">DHL</span> @endif
                                    </p>
                                </div>

                                <form method="POST" action="{{ route('panel.order.hapus-kiriman', [$order, $kiriman]) }}"
                                      onsubmit="return confirm('Hapus pengiriman ini beserta linimasanya?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-[0.75rem] text-merah-500">hapus</button>
                                </form>
                            </div>

                            @if ($kiriman->status)
                                <p class="mt-1 text-[0.75rem] text-slate-600">{{ $kiriman->status_description ?: $kiriman->status }}</p>
                            @endif

                            <p class="mt-1 text-[0.7rem] text-slate-400">
                                {{ $kiriman->events->count() }} peristiwa
                                @if ($kiriman->last_checked) &middot; dicek {{ $kiriman->last_checked->diffForHumans() }} @endif
                            </p>
                        </div>
                    @empty
                        <p class="py-4 text-center text-[0.8125rem] text-slate-400">Belum ada pengiriman.</p>
                    @endforelse
                </div>
            @endif
        </section>
    </div>

    @unless ($baru)
        <form method="POST" action="{{ route('panel.order.destroy', $order) }}" class="mt-4"
              onsubmit="return confirm('Hapus order ini beserta pengiriman dan linimasanya?')">
            @csrf
            @method('DELETE')
            <button class="tombol bg-white text-merah-500 shadow-[var(--shadow-timbul-kecil)]">Hapus order</button>
        </form>
    @endunless
</x-panel.layout>
