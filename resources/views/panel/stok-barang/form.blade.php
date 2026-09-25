@php $baru = ! $barang->exists; @endphp

<x-panel.layout :judul="$baru ? 'Tambah Stok Barang' : 'Ubah Stok Barang'"
                :keterangan="$baru ? null : $barang->name">

    <x-slot:aksi>
        <a href="{{ route('panel.stok-barang.index') }}" class="tombol tombol-hening">Batal</a>
        <button type="submit" form="form-stok" class="tombol tombol-utama">Simpan</button>
    </x-slot:aksi>

    <form id="form-stok" method="POST" class="max-w-3xl space-y-4"
          action="{{ $baru ? route('panel.stok-barang.store') : route('panel.stok-barang.update', $barang) }}">
        @csrf
        @unless ($baru) @method('PUT') @endunless

        <section class="kartu p-5">
            <h2 class="mb-4 text-sm font-semibold text-slate-800">Identitas</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="label">Nama Barang</label>
                    <input id="name" name="name" class="isian" required maxlength="255" value="{{ old('name', $barang->name) }}">
                    @error('name') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="sku" class="label">Kode / SKU</label>
                    <input id="sku" name="sku" class="isian" required maxlength="64" value="{{ old('sku', $barang->sku) }}">
                    @error('sku') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="unit" class="label">Satuan</label>
                    <input id="unit" name="unit" class="isian" required value="{{ old('unit', $barang->unit ?? 'pcs') }}">
                </div>

                <div>
                    <label for="item_category_id" class="label">Kategori <span class="font-normal text-slate-400">(boleh kosong)</span></label>
                    <select id="item_category_id" name="item_category_id" class="isian">
                        <option value="">— belum dipilih —</option>
                        @foreach ($daftarKategori as $id => $nama)
                            <option value="{{ $id }}" @selected((string) old('item_category_id', $barang->item_category_id) === (string) $id)>{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="item_type_id" class="label">Produk <span class="font-normal text-slate-400">(boleh kosong)</span></label>
                    <select id="item_type_id" name="item_type_id" class="isian">
                        <option value="">— belum dipilih —</option>
                        @foreach ($daftarJenis as $id => $nama)
                            <option value="{{ $id }}" @selected((string) old('item_type_id', $barang->item_type_id) === (string) $id)>{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="kartu p-5">
            <h2 class="mb-1 text-sm font-semibold text-slate-800">Harga &amp; Stok</h2>
            <p class="mb-4 text-[0.8125rem] text-slate-500">
                Stok tidak bisa diketik di sini — angkanya hanya bergerak lewat pembelian, penjualan, atau stok opname.
            </p>

            <div class="grid gap-4 sm:grid-cols-3">
                @foreach (['cost_price' => 'Harga Modal', 'sell_price' => 'Harga Jual'] as $medan => $label)
                    <div>
                        <label for="{{ $medan }}" class="label">{{ $label }}</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400">Rp</span>
                            <input id="{{ $medan }}" name="{{ $medan }}" inputmode="decimal" required class="isian pl-10"
                                   value="{{ old($medan, (float) ($barang->{$medan} ?? 0)) }}">
                        </div>
                        @error($medan) <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                    </div>
                @endforeach

                <div>
                    <label for="min_stock" class="label">Stok minimum</label>
                    <input id="min_stock" name="min_stock" inputmode="decimal" class="isian"
                           value="{{ old('min_stock', (float) ($barang->min_stock ?? 0)) }}">
                </div>

                @unless ($baru)
                    <div class="sm:col-span-3">
                        <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.07em] text-slate-400">Stok saat ini</p>
                        <p class="mt-1 text-lg font-bold text-slate-800">
                            {{ rtrim(rtrim(number_format((float) $barang->stock, 2, ',', '.'), '0'), ',') }}
                            <span class="text-sm font-normal text-slate-400">{{ $barang->unit }}</span>
                        </p>
                    </div>
                @endunless

                <div class="sm:col-span-3">
                    <label for="notes" class="label">Catatan</label>
                    <textarea id="notes" name="notes" rows="3" class="isian">{{ old('notes', $barang->notes) }}</textarea>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-3">
                    <input type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $barang->exists ? $barang->is_active : true))
                           class="rounded border-slate-300 text-ungu-500 focus:ring-ungu-400">
                    Aktif
                </label>
            </div>
        </section>
    </form>

    @unless ($baru)
        <form method="POST" action="{{ route('panel.stok-barang.destroy', $barang) }}" class="mt-4 max-w-3xl"
              onsubmit="return confirm('Hapus barang ini? Tindakan ini tidak bisa dibatalkan.')">
            @csrf
            @method('DELETE')
            <button class="tombol bg-white text-merah-500 shadow-[var(--shadow-timbul-kecil)]">Hapus barang</button>
        </form>
    @endunless
</x-panel.layout>
