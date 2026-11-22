@extends('layouts.admin', ['title' => $part->exists ? 'Ubah komponen' : 'Komponen baru', 'subtitle' => 'Isi bahan baku yang dibutuhkan untuk membuat 1 komponen, supaya stok bahan bisa terpotong otomatis saat komponen dibuat.'])

@php
    $lines = old('lines', $part->materials->map(fn ($line) => ['material_id' => $line->material_id, 'qty' => (float) $line->qty])->all());
    $lines = array_values($lines);
    if (count($lines) === 0) {
        $lines = [['material_id' => '', 'qty' => '']];
    }
@endphp

@section('content')
    <form method="POST" action="{{ $part->exists ? route('admin.components.update', $part) : route('admin.components.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($part->exists) @method('PUT') @endif

        <section class="card space-y-4 p-6">
            <h2 class="card-title">Data komponen</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.field name="code" label="Kode" :value="$part->code" required placeholder="mis. KMP-LHR" />
                <x-admin.field name="name" label="Nama komponen" :value="$part->name" required class="sm:col-span-2" placeholder="mis. Leher / Header" />
                <x-admin.select name="group" label="Kelompok" :options="\App\Models\Component::GROUPS" :value="$part->group" :placeholder="false" required />
                <x-admin.field name="unit" label="Satuan" :value="$part->unit" required />
                <x-admin.field name="min_stock" label="Stok minimum" type="number" step="any" min="0" :value="(float) $part->min_stock" hint="Status MENIPIS bila stok di bawah ini." />
                <x-admin.field name="description" label="Keterangan" :value="$part->description" class="sm:col-span-2" />
                <x-admin.field name="sort_order" label="Urutan" type="number" min="0" :value="(int) $part->sort_order" />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $part->is_active))> Aktif
            </label>
        </section>

        <section class="card space-y-4 p-6" data-bom>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="card-title">Bahan baku penyusun</h2>
                    <p class="text-xs text-slate-500">Jumlah bahan untuk membuat <strong>1 {{ $part->unit ?: 'unit' }}</strong> komponen, dalam satuan stok bahan.</p>
                </div>
                <button type="button" class="btn btn-sm" data-bom-add>+ Baris bahan</button>
            </div>

            <div class="space-y-2" data-bom-rows>
                @foreach ($lines as $i => $line)
                    <div class="grid grid-cols-[minmax(0,1fr)_7rem_auto] items-start gap-2" data-bom-row>
                        <div>
                            <select name="lines[{{ $i }}][material_id]" class="input" aria-label="Bahan baku">
                                <option value="">— pilih bahan —</option>
                                @foreach ($materials as $material)
                                    <option value="{{ $material->id }}" @selected((string) ($line['material_id'] ?? '') === (string) $material->id)>{{ $material->name }} ({{ $material->unit }})</option>
                                @endforeach
                            </select>
                            @error("lines.$i.material_id")<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <input name="lines[{{ $i }}][qty]" type="number" step="any" min="0" value="{{ $line['qty'] ?? '' }}" class="input" placeholder="Jumlah" aria-label="Jumlah">
                            @error("lines.$i.qty")<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <button type="button" class="btn btn-ghost px-2 text-rose-600" data-bom-remove aria-label="Hapus baris">✕</button>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-slate-500">Bahan belum ada? Tambahkan dulu di <a href="{{ route('admin.materials.create') }}" class="link" target="_blank">Stok Bahan</a>.</p>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ $part->exists ? route('admin.components.show', $part) : route('admin.components.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>

    @if ($part->exists && ! $part->movements()->exists())
        <div class="max-w-4xl border-t border-slate-200 pt-4">
            <x-admin.delete-button :action="route('admin.components.destroy', $part)" label="Hapus komponen" />
        </div>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        var box = document.querySelector('[data-bom]');
        if (!box) return;
        var rows = box.querySelector('[data-bom-rows]');
        var next = rows.children.length;

        box.querySelector('[data-bom-add]').addEventListener('click', function () {
            var row = rows.querySelector('[data-bom-row]').cloneNode(true);
            row.querySelectorAll('select, input').forEach(function (el) {
                el.name = el.name.replace(/lines\[\d+\]/, 'lines[' + next + ']');
                el.value = '';
            });
            row.querySelectorAll('.error').forEach(function (el) { el.remove(); });
            rows.appendChild(row);
            next++;
            row.querySelector('select').focus();
        });

        rows.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-bom-remove]');
            if (!btn) return;
            var row = btn.closest('[data-bom-row]');
            if (rows.children.length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('select, input').forEach(function (el) { el.value = ''; });
            }
        });
    })();
</script>
@endpush
