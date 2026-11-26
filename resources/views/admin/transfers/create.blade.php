@extends('layouts.admin', ['title' => 'Pindah barang', 'subtitle' => 'Pindahkan stok dari satu gudang ke gudang lain. Jumlah total dan nilainya tetap; pipa dipindah per potongan.'])

@section('content')
    <form method="POST" action="{{ route('admin.transfers.store') }}" class="max-w-4xl space-y-6" data-rows-form data-transfer-form>
        @csrf
        <section class="card space-y-4 p-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field name="transferred_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                <x-admin.field name="moved_by" label="Dipindah oleh" />
                <x-admin.select name="from_warehouse_id" label="Dari gudang" :options="$warehouses" :value="$from" :placeholder="false" required data-from />
                <x-admin.select name="to_warehouse_id" label="Ke gudang" :options="$warehouses" :value="old('to_warehouse_id')" placeholder="— pilih gudang tujuan —" required />
            </div>
        </section>

        <section class="card space-y-3 p-6">
            <div class="flex items-center justify-between">
                <h2 class="card-title">Barang satuan</h2>
                <button type="button" class="btn btn-sm" data-add-row>+ Barang</button>
            </div>
            <div class="space-y-2" data-rows>
                @foreach ($lines as $i => $line)
                    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_9rem_auto] sm:items-start" data-row>
                        <div>
                            <select name="items[{{ $i }}][material_id]" class="input" data-material aria-label="Barang">
                                <option value="">— pilih barang —</option>
                                @foreach ($materials as $material)
                                    <option value="{{ $material->id }}" data-unit="{{ $material->stockUnit() }}"
                                        data-stock="{{ json_encode($material->stocks->mapWithKeys(fn ($stock) => [$stock->warehouse_id => (float) $stock->qty])) }}"
                                        @selected((string) ($line['material_id'] ?? '') === (string) $material->id)>{{ $material->label() }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-slate-500" data-available></p>
                        </div>
                        <input name="items[{{ $i }}][qty]" type="number" step="any" min="0" value="{{ $line['qty'] ?? '' }}" class="input" placeholder="Jumlah" aria-label="Jumlah">
                        <button type="button" class="btn btn-ghost px-2 text-rose-600" data-remove-row aria-label="Hapus baris">✕</button>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card space-y-3 p-6">
            <h2 class="card-title">Potongan pipa</h2>
            <p class="text-xs text-slate-500">Pipa hanya bisa disimpan di Gudang Bahan Baku atau Gudang Stok Sisa. Centang potongan yang dipindah.</p>
            <div class="grid gap-2 sm:grid-cols-2" data-pieces>
                @foreach ($pieces as $piece)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm" data-piece-warehouse="{{ $piece->warehouse_id }}">
                        <input type="checkbox" name="pieces[]" value="{{ $piece->id }}" @checked(in_array((string) $piece->id, array_map('strval', old('pieces', [])), true))>
                        <span><span class="font-mono text-xs">{{ $piece->code }}</span> · {{ $piece->material->label() }} · <span class="font-semibold">{{ qty($piece->length_cm, 2) }} cm</span></span>
                    </label>
                @endforeach
            </div>
            <p class="hidden text-sm text-slate-500" data-no-pieces>Tidak ada potongan pipa di gudang asal.</p>
        </section>

        <section class="card p-6"><x-admin.field name="notes" label="Catatan" type="textarea" /></section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.transfers.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Pindahkan</button>
        </div>
    </form>
@endsection

@push('scripts')
    @include('admin.partials.repeat-rows')
    <script>
        (function () {
            var form = document.querySelector('[data-transfer-form]');
            var from = form.querySelector('[data-from]');

            function refresh() {
                var wh = from.value;
                form.querySelectorAll('[data-row]').forEach(function (row) {
                    var opt = row.querySelector('[data-material]').selectedOptions[0];
                    var info = row.querySelector('[data-available]');
                    if (!opt || !opt.value) { info.textContent = ''; return; }
                    var stock = JSON.parse(opt.dataset.stock || '{}')[wh] || 0;
                    info.textContent = 'Stok di gudang asal: ' + stock.toLocaleString('id-ID') + ' ' + opt.dataset.unit;
                    info.classList.toggle('text-rose-600', stock <= 0);
                });
                var shown = 0;
                form.querySelectorAll('[data-piece-warehouse]').forEach(function (el) {
                    var visible = el.dataset.pieceWarehouse === wh;
                    el.classList.toggle('hidden', !visible);
                    if (!visible) el.querySelector('input').checked = false;
                    shown += visible ? 1 : 0;
                });
                form.querySelector('[data-no-pieces]').classList.toggle('hidden', shown > 0);
            }

            form.addEventListener('change', refresh);
            form.addEventListener('row:added', refresh);
            refresh();
        })();
    </script>
@endpush
