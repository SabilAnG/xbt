<x-panel.layout judul="Nota Pembelian Bahan Baru"
                keterangan="Nota dibuat sebagai draft. Bahannya ditambahkan setelah ini.">

    <x-slot:aksi>
        <a href="{{ route('panel.pembelian-bahan.index') }}" class="tombol tombol-hening">Batal</a>
        <button type="submit" form="form-nota" class="tombol tombol-utama">Buat draft</button>
    </x-slot:aksi>

    <form id="form-nota" method="POST" action="{{ route('panel.pembelian-bahan.store') }}"
          class="kartu grid max-w-2xl gap-4 p-5 sm:grid-cols-2">
        @csrf

        <div>
            <label for="invoice_number" class="label">Nomor nota</label>
            <input id="invoice_number" name="invoice_number" class="isian" required maxlength="64"
                   value="{{ old('invoice_number', $nomor) }}">
            @error('invoice_number') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="purchased_at" class="label">Tanggal</label>
            <input id="purchased_at" name="purchased_at" type="date" class="isian" required
                   value="{{ old('purchased_at', now()->format('Y-m-d')) }}">
            @error('purchased_at') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="supplier_name" class="label">Pemasok</label>
            <input id="supplier_name" name="supplier_name" class="isian" maxlength="255" value="{{ old('supplier_name') }}">
        </div>

        <div>
            <label for="warehouse_id" class="label">Gudang tujuan</label>
            <select id="warehouse_id" name="warehouse_id" class="isian" required>
                <option value="">— pilih gudang —</option>
                @foreach ($daftarGudang as $id => $nama)
                    <option value="{{ $id }}" @selected((string) old('warehouse_id') === (string) $id)>{{ $nama }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-[0.75rem] text-slate-400">Bahan yang dibeli harus mendarat di sebuah gudang.</p>
            @error('warehouse_id') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="wallet_id" class="label">Dibayar dari</label>
            <select id="wallet_id" name="wallet_id" class="isian">
                <option value="">— belum dipilih —</option>
                @foreach ($daftarKas as $id => $nama)
                    <option value="{{ $id }}" @selected((string) old('wallet_id') === (string) $id)>{{ $nama }}</option>
                @endforeach
            </select>
        </div>

        <div class="sm:col-span-2">
            <label for="notes" class="label">Catatan</label>
            <textarea id="notes" name="notes" rows="2" class="isian">{{ old('notes') }}</textarea>
        </div>
    </form>
</x-panel.layout>
