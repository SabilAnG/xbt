<x-panel.layout :judul="$nota->invoice_number" :keterangan="$nota->supplier_name ?: 'Tanpa pemasok'">

    <x-slot:aksi>
        <a href="{{ route('panel.pembelian-bahan.index') }}" class="tombol tombol-hening">Kembali</a>

        @if ($nota->isPosted())
            <form method="POST" action="{{ route('panel.pembelian-bahan.batalkan', $nota) }}"
                  onsubmit="return confirm('Batalkan pembukuan? Stok dan kas akan dihitung ulang.')">
                @csrf
                <button class="tombol border border-amber-200 bg-white text-amber-700 hover:bg-amber-50">
                    Batalkan pembukuan
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('panel.pembelian-bahan.bukukan', $nota) }}"
                  onsubmit="return confirm('Bukukan nota ini? Stok gudang naik dan kas turun.')">
                @csrf
                <button class="tombol tombol-utama">Bukukan</button>
            </form>
        @endif
    </x-slot:aksi>

    {{-- Ringkasan --}}
    <section class="kartu mb-4 grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            'Status' => null,
            'Tanggal' => $nota->purchased_at?->format('d F Y') ?: '—',
            'Gudang' => $nota->warehouse?->name ?: '—',
            'Kas' => $nota->wallet?->name ?: '—',
        ] as $label => $isi)
            <div>
                <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.07em] text-slate-400">{{ $label }}</p>

                @if ($label === 'Status')
                    <p class="mt-1">
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-[0.8125rem] font-medium',
                            'bg-mint-400/15 text-emerald-700' => $nota->isPosted(),
                            'bg-amber-50 text-amber-700' => ! $nota->isPosted(),
                        ])>{{ $nota->displayStatus() }}</span>
                    </p>
                @else
                    <p class="mt-1 text-sm font-medium text-slate-800">{{ $isi }}</p>
                @endif
            </div>
        @endforeach
    </section>

    {{-- Baris barang --}}
    <div class="kartu overflow-hidden">
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th class="th">Bahan</th>
                    <th class="th">Gudang</th>
                    <th class="th text-right">Qty</th>
                    <th class="th text-right">Harga Satuan</th>
                    <th class="th text-right">Subtotal</th>
                    <th class="th w-px"></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($nota->items as $baris)
                    <tr>
                        <td class="td">
                            <p class="font-medium text-slate-800">{{ $baris->item?->name ?: 'Bahan terhapus' }}</p>
                            <p class="text-[0.75rem] text-slate-400">{{ $baris->item?->sku }}</p>
                        </td>
                        <td class="td text-slate-600">{{ $baris->warehouse?->name ?: '—' }}</td>
                        <td class="td text-right text-slate-700">
                            {{ rtrim(rtrim(number_format((float) $baris->qty, 3, ',', '.'), '0'), ',') }}
                            <span class="text-slate-400">{{ $baris->item?->unit }}</span>
                        </td>
                        <td class="td text-right text-slate-700">Rp {{ number_format((float) $baris->unit_cost, 0, ',', '.') }}</td>
                        <td class="td text-right font-medium text-slate-800">Rp {{ number_format((float) $baris->subtotal, 0, ',', '.') }}</td>
                        <td class="td text-right">
                            @unless ($nota->isPosted())
                                <form method="POST" action="{{ route('panel.pembelian-bahan.hapus-baris', [$nota, $baris->id]) }}"
                                      onsubmit="return confirm('Hapus baris ini?')">
                                    @csrf @method('DELETE')
                                    <button class="text-[0.75rem] text-merah-500">hapus</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">
                            Nota ini belum punya baris barang.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @unless ($nota->isPosted())
            <form method="POST" action="{{ route('panel.pembelian-bahan.tambah-baris', $nota) }}"
                  class="flex flex-wrap items-end gap-2 border-t border-slate-100 p-4">
                @csrf

                <label class="min-w-0 flex-1 basis-56">
                    <span class="label">Bahan</span>
                    <select name="production_item_id" class="isian" required>
                        <option value="">— pilih bahan —</option>
                        @foreach ($daftarBahan as $b)
                            <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->unit }})</option>
                        @endforeach
                    </select>
                </label>

                <label class="w-40">
                    <span class="label">Gudang</span>
                    <select name="warehouse_id" class="isian">
                        <option value="">ikut nota</option>
                        @foreach ($daftarGudang as $id => $nama)
                            <option value="{{ $id }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="w-28">
                    <span class="label">Jumlah</span>
                    <input name="qty" inputmode="decimal" class="isian" required>
                </label>

                <label class="w-36">
                    <span class="label">Harga satuan</span>
                    <input name="unit_cost" inputmode="decimal" class="isian" required>
                </label>

                <button class="tombol tombol-utama">Tambah</button>
            </form>
        @endunless

        {{-- Total --}}
        <div class="border-t border-slate-100 bg-slate-50/60 px-4 py-3">
            <dl class="ml-auto max-w-xs space-y-1 text-sm">
                @foreach (['Subtotal' => $nota->subtotal, 'Diskon' => $nota->discount, 'Ongkos kirim' => $nota->shipping_cost] as $label => $nilai)
                    <div class="flex justify-between text-slate-600">
                        <dt>{{ $label }}</dt>
                        <dd>Rp {{ number_format((float) $nilai, 0, ',', '.') }}</dd>
                    </div>
                @endforeach

                <div class="flex justify-between border-t border-slate-200 pt-1 text-base font-semibold text-slate-900">
                    <dt>Total</dt>
                    <dd>Rp {{ number_format((float) $nota->total, 0, ',', '.') }}</dd>
                </div>
            </dl>
        </div>
    </div>

    @if ($nota->notes)
        <section class="kartu mt-4 p-5">
            <h2 class="mb-1 text-sm font-semibold text-slate-800">Catatan</h2>
            <p class="whitespace-pre-line text-sm text-slate-600">{{ $nota->notes }}</p>
        </section>
    @endif
</x-panel.layout>
