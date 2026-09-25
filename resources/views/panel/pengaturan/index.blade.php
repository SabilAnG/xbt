@php $aman = fn (string $kunci) => str_replace('.', '__', $kunci); @endphp

<x-panel.layout judul="Pengaturan Situs"
                keterangan="Nama, logo, kontak, dan nomor WhatsApp yang dipakai situs publik — dan panel ini sendiri.">

    <x-slot:aksi>
        <a href="{{ url('/') }}" target="_blank" rel="noopener" class="tombol tombol-hening">Lihat situs</a>
        <button type="submit" form="form-pengaturan" class="tombol tombol-utama">Simpan</button>
    </x-slot:aksi>

    <form id="form-pengaturan" method="POST" action="{{ route('panel.pengaturan.update') }}"
          enctype="multipart/form-data" class="grid max-w-5xl gap-4 lg:grid-cols-2">
        @csrf
        @method('PUT')

        @foreach ($medan as $judul => $isian)
            <section class="kartu p-5">
                <h2 class="mb-4 text-sm font-semibold text-slate-800">{{ $judul }}</h2>

                <div class="space-y-3">
                    @foreach ($isian as $kunci => [$label, $jenis])
                        @php $nama = 'isian['.$aman($kunci).']'; @endphp
                        <div>
                            <label for="{{ $aman($kunci) }}" class="label">{{ $label }}</label>
                            <input id="{{ $aman($kunci) }}" name="{{ $nama }}" class="isian"
                                   type="{{ $jenis === 'email' ? 'email' : ($jenis === 'url' ? 'url' : 'text') }}"
                                   value="{{ old('isian.'.$aman($kunci), $nilai[$kunci]) }}">
                            @error('isian.'.$aman($kunci))
                                <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        {{-- Gambar --}}
        <section class="kartu p-5 lg:col-span-2">
            <h2 class="mb-1 text-sm font-semibold text-slate-800">Logo &amp; Favicon</h2>
            <p class="mb-4 text-[0.8125rem] text-slate-500">
                Dikosongkan berarti yang sekarang tetap dipakai. Logo lama tidak dihapus — berkas bawaan
                di <code>assets/images/</code> masih dipakai halaman lain.
            </p>

            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($gambar as $kunci => $label)
                    <div>
                        <p class="label">{{ $label }}</p>

                        <div class="mb-2 grid h-20 place-items-center rounded-xl bg-lantai shadow-[var(--shadow-cekung)]">
                            @if ($nilai[$kunci])
                                <img src="{{ asset($nilai[$kunci]) }}" alt="" class="max-h-16 max-w-full object-contain">
                            @else
                                <span class="text-[0.75rem] text-slate-400">belum ada</span>
                            @endif
                        </div>

                        <input type="file" name="gambar[{{ $aman($kunci) }}]" accept="image/*"
                               class="w-full rounded-xl bg-lantai px-3 py-2 text-[0.75rem] text-slate-600 shadow-[var(--shadow-cekung)] file:mr-2 file:rounded-full file:border-0 file:bg-ungu-500 file:px-2.5 file:py-1 file:text-[0.7rem] file:font-semibold file:text-white">

                        @error('gambar.'.$aman($kunci))
                            <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p>
                        @enderror

                        <p class="mt-1 truncate text-[0.7rem] text-slate-400">{{ $nilai[$kunci] ?: '—' }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </form>
</x-panel.layout>
