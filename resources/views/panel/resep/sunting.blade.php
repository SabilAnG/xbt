{{-- Panel kanan halaman resep: sunting kepala, tambah bahan, tambah jasa. --}}

<section class="kartu p-5">
    <h2 class="mb-3 text-sm font-semibold text-slate-800">Keterangan resep</h2>

    <form id="form-resep" method="POST" action="{{ route('panel.resep.update', $resep) }}" class="space-y-3">
        @csrf @method('PUT')

        <div>
            <label for="name" class="label">Nama</label>
            <input id="name" name="name" class="isian" required value="{{ old('name', $resep->name) }}">
            @error('name') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="code" class="label">Kode</label>
            <input id="code" name="code" class="isian" value="{{ old('code', $resep->code) }}">
            @error('code') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="motorcycle_model_id" class="label">Type Motor</label>
            <select id="motorcycle_model_id" name="motorcycle_model_id" class="isian">
                <option value="">— belum dipilih —</option>
                @foreach ($daftarMotor as $id => $nama)
                    <option value="{{ $id }}" @selected((string) old('motorcycle_model_id', $resep->motorcycle_model_id) === (string) $id)>{{ $nama }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label for="output_qty" class="label">Hasil</label>
                <input id="output_qty" name="output_qty" inputmode="decimal" class="isian" required
                       value="{{ old('output_qty', (float) ($resep->output_qty ?: 1)) }}">
            </div>
            <div>
                <label for="output_unit" class="label">Satuan</label>
                <input id="output_unit" name="output_unit" class="isian" required
                       value="{{ old('output_unit', $resep->output_unit ?: 'pcs') }}">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $resep->is_active))
                   class="rounded border-slate-300 text-ungu-500 focus:ring-ungu-400">
            Aktif
        </label>
    </form>
</section>

{{-- Tambah bahan. Isian ukuran mengikuti bentuk bahan yang dipilih, sama
     seperti di master barang — pipa dipotong sepanjang sekian, plat sekian
     kali sekian, sisanya per buah. --}}
<section class="kartu p-5">
    <h2 class="mb-3 text-sm font-semibold text-slate-800">Tambah bahan</h2>

    <form method="POST" action="{{ route('panel.resep.tambah-baris', $resep) }}" class="space-y-2">
        @csrf

        <select name="production_item_id" class="isian" required data-bentuk-pilihan>
            <option value="">— pilih bahan —</option>
            @foreach ($daftarBahan as $bahan)
                <option value="{{ $bahan->id }}" data-bentuk-bahan="{{ $bahan->shape }}">
                    {{ $bahan->name }}
                </option>
            @endforeach
        </select>

        <select name="exhaust_component_id" class="isian">
            <option value="">— bagian mana? (boleh kosong) —</option>
            @foreach ($daftarBagian as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>

        <div class="grid grid-cols-2 gap-2">
            <input name="piece_length_mm" inputmode="decimal" class="isian" placeholder="Panjang (mm)">
            <input name="piece_width_mm" inputmode="decimal" class="isian" placeholder="Lebar (mm)">
        </div>

        <input name="piece_count" inputmode="decimal" class="isian" required value="1" placeholder="Jumlah potong / buah">

        <p class="text-[0.75rem] text-slate-400">
            Bahan batangan butuh panjang; lembaran butuh panjang dan lebar; sisanya cukup jumlahnya.
        </p>

        <button class="tombol tombol-utama w-full">Tambah bahan</button>
    </form>
</section>

<section class="kartu p-5">
    <h2 class="mb-3 text-sm font-semibold text-slate-800">Tambah jasa</h2>

    <form method="POST" action="{{ route('panel.resep.tambah-jasa', $resep) }}" class="space-y-2">
        @csrf
        <select name="production_service_id" class="isian" required>
            <option value="">— pilih jasa —</option>
            @foreach ($daftarJasa as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>
        <input name="qty" inputmode="decimal" class="isian" required value="1" placeholder="Jumlah">
        <button class="tombol tombol-hening w-full">Tambah jasa</button>
    </form>
</section>
