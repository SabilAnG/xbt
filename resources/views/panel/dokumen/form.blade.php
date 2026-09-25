<x-panel.layout :judul="'Nota '.$spek['judul'].' Baru'"
                keterangan="Nota dibuat sebagai draft. Barisnya ditambahkan setelah ini.">

    <x-slot:aksi>
        <a href="{{ route('panel.dokumen.index', $jenis) }}" class="tombol tombol-hening">Batal</a>
        <button type="submit" form="form-nota" class="tombol tombol-utama">Buat draft</button>
    </x-slot:aksi>

    <form id="form-nota" method="POST" action="{{ route('panel.dokumen.store', $jenis) }}"
          class="kartu grid max-w-2xl gap-4 p-5 sm:grid-cols-2">
        @csrf

        <div>
            <label for="nomor" class="label">Nomor nota</label>
            <input id="nomor" name="{{ $spek['nomor'] }}" class="isian" required maxlength="64"
                   value="{{ old($spek['nomor'], $nomor) }}">
            <p class="mt-1 text-[0.75rem] text-slate-400">Dibuat otomatis, boleh diganti.</p>
            @error($spek['nomor']) <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="tanggal" class="label">Tanggal</label>
            <input id="tanggal" name="{{ $spek['tanggal'] }}" type="date" class="isian" required
                   value="{{ old($spek['tanggal'], now()->format('Y-m-d')) }}">
            @error($spek['tanggal']) <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
        </div>

        @foreach ($spek['kepala'] as $medan => [$label, $jenisIsian])
            <div @class(['sm:col-span-2' => $jenisIsian === 'teks' && $medan === 'description'])>
                <label for="{{ $medan }}" class="label">{{ $label }}</label>

                @if ($jenisIsian === 'dompet' || $jenisIsian === 'kategori_pengeluaran')
                    <select id="{{ $medan }}" name="{{ $medan }}" class="isian">
                        <option value="">— belum dipilih —</option>
                        @foreach ($pilihan[$jenisIsian === 'dompet' ? 'dompet' : 'kategori_pengeluaran'] as $id => $nama)
                            <option value="{{ $id }}" @selected((string) old($medan) === (string) $id)>{{ $nama }}</option>
                        @endforeach
                    </select>
                @elseif ($jenisIsian === 'angka')
                    <div class="relative">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400">Rp</span>
                        <input id="{{ $medan }}" name="{{ $medan }}" inputmode="decimal" required class="isian pl-10"
                               value="{{ old($medan) }}">
                    </div>
                @else
                    <input id="{{ $medan }}" name="{{ $medan }}" class="isian" maxlength="255" value="{{ old($medan) }}">
                @endif

                @error($medan) <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </form>
</x-panel.layout>
