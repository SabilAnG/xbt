@php $dibukukan = $nota->status === 'posted'; @endphp

<x-panel.layout :judul="$nota->{$spek['nomor']} ?: $spek['judul']"
                :keterangan="$nota->{$spek['pihak']['kolom']} ?: null">

    <x-slot:aksi>
        <a href="{{ route('panel.dokumen.index', $jenis) }}" class="tombol tombol-hening">Kembali</a>

        @if (! $dibukukan)
            <form method="POST" action="{{ route('panel.dokumen.bukukan', [$jenis, $nota->id]) }}"
                  onsubmit="return confirm('Bukukan nota ini? Stok dan kas akan bergerak.')">
                @csrf
                <button class="tombol tombol-utama">Bukukan</button>
            </form>
        @elseif ($spek['unpost'])
            <form method="POST" action="{{ route('panel.dokumen.batalkan', [$jenis, $nota->id]) }}"
                  onsubmit="return confirm('Batalkan pembukuan? Stok dan kas akan dihitung ulang.')">
                @csrf
                <button class="tombol bg-white text-amber-700 shadow-[var(--shadow-timbul-kecil)]">Batalkan pembukuan</button>
            </form>
        @endif
    </x-slot:aksi>

    <section class="kartu mb-4 grid gap-4 p-5 sm:grid-cols-4">
        <div>
            <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.07em] text-slate-400">Status</p>
            <p class="mt-1">
                <span @class([
                    'rounded-full px-2.5 py-0.5 text-[0.8125rem] font-medium',
                    'bg-mint-500/15 text-emerald-700' => $dibukukan,
                    'bg-kuning-400/25 text-amber-700' => ! $dibukukan,
                ])>{{ $dibukukan ? 'Dibukukan' : 'Draft' }}</span>
            </p>
            @if ($dibukukan && ! $spek['unpost'])
                <p class="mt-1 text-[0.75rem] text-slate-400">Tidak bisa dibatalkan.</p>
            @endif
        </div>

        @foreach ([
            'Tanggal' => $nota->{$spek['tanggal']}?->format('d F Y') ?: '—',
            $spek['pihak']['label'] => $nota->{$spek['pihak']['kolom']} ?: '—',
        ] as $label => $isi)
            <div>
                <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.07em] text-slate-400">{{ $label }}</p>
                <p class="mt-1 text-sm font-medium text-slate-800">{{ $isi }}</p>
            </div>
        @endforeach

        @if ($spek['total'])
            <div>
                <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.07em] text-slate-400">Nilai</p>
                <p class="mt-1 text-lg font-bold text-slate-900">
                    Rp {{ number_format((float) $nota->{$spek['total']}, 0, ',', '.') }}
                </p>
            </div>
        @endif
    </section>

    @if ($spek['baris'])
        <div class="kartu overflow-hidden">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        <th class="th">Barang</th>
                        @if (isset($spek['baris']['selisih']))
                            <th class="th text-right">Menurut sistem</th>
                            <th class="th text-right">Hasil hitung</th>
                            <th class="th text-right">Selisih</th>
                        @else
                            <th class="th text-right">Qty</th>
                            <th class="th text-right">{{ $spek['baris']['label_harga'] }}</th>
                            <th class="th text-right">Subtotal</th>
                        @endif
                        <th class="th w-px"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($nota->items as $baris)
                        @php $selisih = isset($spek['baris']['selisih']) ? (float) $baris->difference : null; @endphp
                        <tr @class(['bg-kuning-400/10' => $selisih !== null && abs($selisih) > 0.0001])>
                            <td class="td">
                                <p class="font-medium text-slate-800">{{ $baris->item?->name ?: 'Barang terhapus' }}</p>
                                <p class="text-[0.75rem] text-slate-400">{{ $baris->item?->sku }}</p>
                            </td>

                            @if ($selisih !== null)
                                <td class="td text-right text-slate-600">{{ rtrim(rtrim(number_format((float) $baris->system_qty, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="td text-right text-slate-800">{{ rtrim(rtrim(number_format((float) $baris->physical_qty, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="td text-right">
                                    @if (abs($selisih) > 0.0001)
                                        <span @class(['font-semibold', 'text-emerald-600' => $selisih > 0, 'text-merah-500' => $selisih < 0])>
                                            {{ $selisih > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($selisih, 2, ',', '.'), '0'), ',') }}
                                        </span>
                                    @else
                                        <span class="text-slate-300">cocok</span>
                                    @endif
                                </td>
                            @else
                                <td class="td text-right text-slate-700">{{ rtrim(rtrim(number_format((float) $baris->{$spek['baris']['qty']}, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="td text-right text-slate-700">Rp {{ number_format((float) $baris->{$spek['baris']['harga']}, 0, ',', '.') }}</td>
                                <td class="td text-right font-medium text-slate-800">Rp {{ number_format((float) $baris->subtotal, 0, ',', '.') }}</td>
                            @endif

                            <td class="td text-right">
                                @unless ($dibukukan)
                                    <form method="POST" action="{{ route('panel.dokumen.hapus-baris', [$jenis, $nota->id, $baris->id]) }}"
                                          onsubmit="return confirm('Hapus baris ini?')">
                                        @csrf @method('DELETE')
                                        <button class="text-[0.75rem] text-merah-500">hapus</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">Nota ini belum punya baris.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Tambah baris. Hanya selama draft: nota yang sudah dibukukan
                 sudah menggerakkan stok, jadi mengubah barisnya diam-diam akan
                 membuat kartu stok tidak lagi cocok dengan notanya. --}}
            @unless ($dibukukan)
                <form method="POST" action="{{ route('panel.dokumen.tambah-baris', [$jenis, $nota->id]) }}"
                      class="flex flex-wrap items-end gap-2 border-t border-slate-100 p-4">
                    @csrf

                    <label class="min-w-0 flex-1 basis-56">
                        <span class="label">Barang</span>
                        <select name="item_id" class="isian" required>
                            <option value="">— pilih barang —</option>
                            @foreach ($daftarBarang as $id => $nama)
                                <option value="{{ $id }}">{{ $nama }}</option>
                            @endforeach
                        </select>
                    </label>

                    @foreach ($spek['baris_isian'] as $medan => [$label, $wajib])
                        <label class="w-36">
                            <span class="label">{{ $label }}</span>
                            <input name="{{ $medan }}" inputmode="decimal" class="isian" @required($wajib)>
                        </label>
                    @endforeach

                    <button class="tombol tombol-utama">Tambah baris</button>
                </form>
            @endunless
        </div>
    @endif

    @if ($nota->notes ?? $nota->description ?? null)
        <section class="kartu mt-4 p-5">
            <h2 class="mb-1 text-sm font-semibold text-slate-800">Catatan</h2>
            <p class="whitespace-pre-line text-sm text-slate-600">{{ $nota->notes ?? $nota->description }}</p>
        </section>
    @endif
</x-panel.layout>
