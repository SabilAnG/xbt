@php
    $baru = ! $barang->exists;
    $satuan = old('size_unit', $barang->size_unit ?? 'mm');

    /* Ukuran disimpan dalam mm; yang ditampilkan adalah angka dalam satuan yang
       dipakai mengetiknya — yang memasukkan 1,5" tidak mengenali "38,1". */
    $ukuran = fn (string $medan) => old($medan, filled($barang->{$medan})
        ? rtrim(rtrim(number_format(App\Models\ProductionItem::fromMm((float) $barang->{$medan}, $satuan), 4, '.', ''), '0'), '.')
        : null);
@endphp

<x-panel.layout :judul="$baru ? 'Tambah Barang Produksi' : 'Ubah Barang Produksi'"
                :keterangan="$baru ? null : $barang->name">

    <x-slot:aksi>
        <a href="{{ route('panel.barang-produksi.index') }}" class="tombol tombol-hening">Batal</a>
        <button type="submit" form="form-barang" class="tombol tombol-utama">Simpan</button>
    </x-slot:aksi>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-medium">Ada {{ $errors->count() }} isian yang perlu dibetulkan.</p>
        </div>
    @endif

    <form id="form-barang" method="POST"
          action="{{ $baru ? route('panel.barang-produksi.store') : route('panel.barang-produksi.update', $barang) }}"
          class="space-y-4">
        @csrf
        @unless ($baru) @method('PUT') @endunless

        {{-- Identitas --}}
        <section class="kartu p-5">
            <h2 class="mb-1 text-sm font-semibold text-slate-800">Identitas</h2>
            <p class="mb-4 text-[0.8125rem] text-slate-500">
                Pilih jenisnya, dan peran serta cara pengadaannya ikut terisi sendiri.
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="label">Nama Barang</label>
                    <input id="name" name="name" class="isian" required maxlength="255"
                           value="{{ old('name', $barang->name) }}" placeholder="Pipa SS 201 Ø28 x 1,2mm">
                    @error('name') <p class="mt-1 text-[0.8125rem] text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="sku" class="label">Kode / SKU</label>
                    <input id="sku" name="sku" class="isian" required maxlength="64"
                           value="{{ old('sku', $barang->sku) }}" placeholder="PIPA-28">
                    @error('sku') <p class="mt-1 text-[0.8125rem] text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="jenis" class="label">Jenis Barang</label>
                    <select id="jenis" name="production_item_category_id" class="isian">
                        <option value="">— belum dipilih —</option>
                        @foreach ($daftarJenis as $id => $nama)
                            <option value="{{ $id }}" @selected((string) old('production_item_category_id', $barang->production_item_category_id) === (string) $id)>{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="role" class="label">Perannya di produk</label>
                    <select id="role" name="role" class="isian" required>
                        @foreach (App\Models\ProductionItem::ROLES as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('role', $barang->role ?? 'utama') === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="source" class="label">Didapat dari</label>
                    <select id="source" name="source" class="isian" required>
                        @foreach (App\Models\ProductionItem::SOURCES as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('source', $barang->source ?? 'beli') === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <label class="flex items-center gap-2 pt-1 text-sm text-slate-700 sm:col-span-2">
                    <input type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $barang->exists ? $barang->is_active : true))
                           class="rounded border-slate-300 text-aksen-500 focus:ring-aksen-200">
                    Aktif
                </label>
            </div>
        </section>

        {{-- Bahan, ukuran, satuan & harga --}}
        <section class="kartu p-5">
            <h2 class="mb-1 text-sm font-semibold text-slate-800">Bahan, Ukuran, Satuan &amp; Harga</h2>
            <p class="mb-4 text-[0.8125rem] text-slate-500">
                Anda membeli per batang atau lembar, tapi memakainya per milimeter. Isi sekali di sini,
                harga per satuan pakai dihitung sendiri.
            </p>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="shape" class="label">Bentuk</label>
                    <select id="shape" name="shape" class="isian" required data-bentuk-pilihan>
                        @foreach (App\Models\ProductionItem::SHAPES as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('shape', $barang->shape ?? 'count') === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[0.75rem] text-slate-400">Menentukan ukuran mana yang berlaku.</p>
                </div>

                <div>
                    <label for="material" class="label">Jenis Bahan</label>
                    <select id="material" name="material" class="isian">
                        <option value="">— belum dicatat —</option>
                        @foreach (App\Models\ProductionItem::MATERIALS as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('material', $barang->material) === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="unit" class="label">Satuan Beli</label>
                    <input id="unit" name="unit" class="isian" required
                           value="{{ old('unit', $barang->unit ?? 'pcs') }}" placeholder="batang, lembar, kg, pcs">
                </div>

                <div data-bentuk="linear sheet count">
                    <label for="size_unit" class="label">Satuan ukuran</label>
                    <select id="size_unit" name="size_unit" class="isian" required>
                        @foreach (App\Models\ProductionItem::SIZE_UNIT_LABELS as $nilai => $label)
                            <option value="{{ $nilai }}" @selected($satuan === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[0.75rem] text-slate-400">Disimpan tetap dalam mm.</p>
                </div>

                <div data-bentuk="linear sheet count">
                    <label for="length_mm" class="label">Panjang</label>
                    <input id="length_mm" name="length_mm" class="isian" inputmode="decimal"
                           value="{{ $ukuran('length_mm') }}" placeholder="6000">
                    @error('length_mm') <p class="mt-1 text-[0.8125rem] text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div data-bentuk="sheet">
                    <label for="width_mm" class="label">Lebar lembar</label>
                    <input id="width_mm" name="width_mm" class="isian" inputmode="decimal"
                           value="{{ $ukuran('width_mm') }}" placeholder="1200">
                    @error('width_mm') <p class="mt-1 text-[0.8125rem] text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div data-bentuk="linear count">
                    <label for="diameter_mm" class="label">Diameter</label>
                    <input id="diameter_mm" name="diameter_mm" class="isian" inputmode="decimal"
                           value="{{ $ukuran('diameter_mm') }}" placeholder="28">
                    <p class="mt-1 text-[0.75rem] text-slate-400">Untuk baut, ini ukuran dratnya: M8 berarti 8.</p>
                </div>

                <div data-bentuk="linear sheet count">
                    <label for="thickness_mm" class="label">Tebal</label>
                    <input id="thickness_mm" name="thickness_mm" class="isian" inputmode="decimal"
                           value="{{ $ukuran('thickness_mm') }}" placeholder="1.2">
                </div>

                <div data-bentuk="weight">
                    <label for="weight_gram" class="label">Berat per satuan beli (gram)</label>
                    <input id="weight_gram" name="weight_gram" class="isian" inputmode="decimal"
                           value="{{ old('weight_gram', $barang->weight_gram) }}" placeholder="1000">
                    @error('weight_gram') <p class="mt-1 text-[0.8125rem] text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div data-bentuk="volume">
                    <label for="volume_ml" class="label">Volume per satuan beli (ml)</label>
                    <input id="volume_ml" name="volume_ml" class="isian" inputmode="decimal"
                           value="{{ old('volume_ml', $barang->volume_ml) }}" placeholder="1000">
                    @error('volume_ml') <p class="mt-1 text-[0.8125rem] text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cost_price" class="label">Harga per Satuan Beli</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">Rp</span>
                        <input id="cost_price" name="cost_price" class="isian pl-9" inputmode="decimal" required
                               value="{{ old('cost_price', (float) ($barang->cost_price ?? 0)) }}">
                    </div>
                    @error('cost_price') <p class="mt-1 text-[0.8125rem] text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>


        {{-- Stok & catatan --}}
        <section class="kartu p-5">
            <h2 class="mb-4 text-sm font-semibold text-slate-800">Stok &amp; Catatan</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="min_stock" class="label">Stok minimum</label>
                    <input id="min_stock" name="min_stock" class="isian" inputmode="decimal"
                           value="{{ old('min_stock', (float) ($barang->min_stock ?? 0)) }}">
                    <p class="mt-1 text-[0.75rem] text-slate-400">Dalam satuan pakai — pipa 12000 berarti 12 meter.</p>
                </div>

                <div>
                    <label for="min_reusable" class="label">Sisa terkecil yang masih terpakai</label>
                    <input id="min_reusable" name="min_reusable" class="isian" inputmode="decimal"
                           value="{{ old('min_reusable', (float) ($barang->min_reusable ?? 0)) }}">
                    <p class="mt-1 text-[0.75rem] text-slate-400">Isi 0 bila semua sisa masih terpakai.</p>
                </div>

                <div class="sm:col-span-2">
                    <label for="notes" class="label">Catatan</label>
                    <textarea id="notes" name="notes" rows="3" class="isian">{{ old('notes', $barang->notes) }}</textarea>
                </div>
            </div>
        </section>

    </form>

    @unless ($baru)
        <form method="POST" action="{{ route('panel.barang-produksi.destroy', $barang) }}"
              class="mt-4 flex justify-end"
              onsubmit="return confirm('Hapus barang ini? Tindakan ini tidak bisa dibatalkan.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="tombol border border-rose-200 bg-white text-rose-600 hover:bg-rose-50">
                Hapus barang
            </button>
        </form>
    @endunless
</x-panel.layout>
