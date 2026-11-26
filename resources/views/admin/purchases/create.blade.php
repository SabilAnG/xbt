@extends('layouts.admin', ['title' => 'Pembelian bahan', 'subtitle' => $plan ? 'Dari kekurangan rencana '.$plan->plan_number : null])

@section('content')
    <form method="POST" action="{{ route('admin.purchases.store') }}" class="max-w-5xl space-y-6" data-rows-form data-purchase-form>
        @csrf
        @if ($plan)<input type="hidden" name="production_plan_id" value="{{ $plan->id }}">@endif

        <section class="card space-y-4 p-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.field name="purchased_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                <div>
                    <label for="supplier" class="label">Supplier</label>
                    <input id="supplier" name="supplier" value="{{ old('supplier') }}" class="input" list="suppliers" placeholder="Pilih atau ketik nama toko">
                    <datalist id="suppliers">@foreach ($suppliers as $name => $address)<option value="{{ $name }}">@endforeach</datalist>
                    <p class="hint">Nama baru otomatis masuk <a href="{{ route('admin.suppliers.index') }}" class="link">Master Supplier</a>.</p>
                </div>
                <div>
                    <label for="supplier_location" class="label">Lokasi toko / supplier</label>
                    <input id="supplier_location" name="supplier_location" value="{{ old('supplier_location') }}" class="input" placeholder="mis. Pasar Kenari, Jakarta" data-location>
                    @error('supplier_location')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-admin.field name="supplier_invoice" label="No. nota supplier" />
                <div>
                    <label for="buyer" class="label">Dibeli oleh</label>
                    <input id="buyer" name="buyer" value="{{ old('buyer') }}" class="input" list="buyers" placeholder="Siapa yang belanja">
                    <datalist id="buyers">@foreach ($buyers as $buyer)<option value="{{ $buyer }}">@endforeach</datalist>
                    @error('buyer')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
            <label class="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                <input type="hidden" name="receive_now" value="0">
                <input type="checkbox" name="receive_now" value="1" class="mt-0.5" @checked(old('receive_now', '1') === '1')>
                <span><span class="font-medium">Barang sudah diterima sekarang</span>
                    <span class="block text-xs text-slate-500">Centang untuk belanja langsung di toko. Hilangkan centang bila barang dipesan dan datang belakangan — terima lewat menu Penerimaan.</span></span>
            </label>
        </section>

        <section class="card space-y-3 p-6">
            <div class="flex items-center justify-between">
                <h2 class="card-title">Barang</h2>
                <button type="button" class="btn btn-sm" data-add-row>+ Barang</button>
            </div>
            @error('items')<p class="error">{{ $message }}</p>@enderror
            <div class="hidden gap-2 text-xs font-medium text-slate-500 sm:grid sm:grid-cols-[minmax(0,1fr)_11rem_6rem_7rem_9rem_8rem_auto]">
                <span>Bahan</span><span>Masuk ke gudang</span><span>Jumlah</span><span>Panjang batang</span><span>Harga satuan</span><span class="text-right">Subtotal</span><span></span>
            </div>
            <div class="space-y-2" data-rows>
                @foreach ($lines as $i => $line)
                    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_11rem_6rem_7rem_9rem_8rem_auto] sm:items-start" data-row>
                        <div>
                            <select name="items[{{ $i }}][material_id]" class="input" data-material aria-label="Bahan">
                                <option value="">— pilih bahan —</option>
                                @foreach ($materials->groupBy(fn ($m) => $m->warehouse->name) as $warehouse => $group)
                                    <optgroup label="{{ $warehouse }}">
                                        @foreach ($group as $material)
                                            <option value="{{ $material->id }}" data-pipe="{{ $material->isPipe() ? 1 : 0 }}" data-unit="{{ $material->purchaseUnit() }}{{ $material->hasConversion() ? ' · '.$material->conversionLabel() : '' }}" data-bar="{{ (float) $material->bar_length_cm }}" data-price="{{ (float) $material->last_price }}" data-warehouse="{{ $material->warehouse_id }}" data-allowed="{{ implode(',', $material->allowedWarehouseIds()) }}"
                                                @selected((string) ($line['material_id'] ?? '') === (string) $material->id)>{{ $material->label() }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error("items.$i.material_id")<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <select name="items[{{ $i }}][warehouse_id]" class="input" data-warehouse aria-label="Masuk ke gudang">
                                @foreach ($warehouses as $id => $name)
                                    <option value="{{ $id }}" @selected((string) ($line['warehouse_id'] ?? '') === (string) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error("items.$i.warehouse_id")<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <div class="flex items-center gap-1">
                                <input name="items[{{ $i }}][qty]" type="number" step="any" min="0" value="{{ $line['qty'] ?? '' }}" class="input" data-qty aria-label="Jumlah">
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500" data-unit-label></p>
                            @error("items.$i.qty")<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <input name="items[{{ $i }}][bar_length_cm]" type="number" step="any" min="0" value="{{ $line['bar_length_cm'] ?? '' }}" class="input" data-bar placeholder="cm" aria-label="Panjang batang (cm)">
                        </div>
                        <div>
                            <input name="items[{{ $i }}][unit_price]" type="number" step="any" min="0" value="{{ $line['unit_price'] ?? '' }}" class="input" data-price aria-label="Harga satuan">
                            @error("items.$i.unit_price")<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <p class="py-2 text-right text-sm font-semibold tabular-nums" data-subtotal>Rp 0</p>
                        <button type="button" class="btn btn-ghost px-2 text-rose-600" data-remove-row aria-label="Hapus baris">✕</button>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end border-t border-slate-100 pt-3 text-base font-semibold">Total&nbsp;<span data-total class="tabular-nums">Rp 0</span></div>
        </section>

        <section class="card space-y-4 p-6">
            <h2 class="card-title">Pembayaran</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.field name="paid_amount" label="Dibayar sekarang (Rp)" type="number" step="any" min="0" hint="Kosongkan bila belum dibayar (hutang)." data-paid />
                <x-admin.wallet-select class="sm:col-span-2" placeholder="— pilih dompet —" />
            </div>
            <button type="button" class="btn btn-sm" data-pay-full>Bayar lunas</button>
            <x-admin.field name="notes" label="Catatan" type="textarea" />
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.purchases.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan pembelian</button>
        </div>
    </form>
@endsection

@push('scripts')
    @include('admin.partials.repeat-rows')
    <script>
        (function () {
            var form = document.querySelector('[data-purchase-form]');
            function rp(v) { return 'Rp ' + Math.round(v).toLocaleString('id-ID'); }
            var total = 0;

            function refresh() {
                total = 0;
                form.querySelectorAll('[data-row]').forEach(function (row) {
                    var opt = row.querySelector('[data-material]').selectedOptions[0];
                    var pipe = opt && opt.dataset.pipe === '1';
                    var allowed = opt && opt.value ? opt.dataset.allowed.split(',') : null;
                    Array.prototype.forEach.call(row.querySelector('[data-warehouse]').options, function (o) {
                        o.hidden = allowed ? allowed.indexOf(o.value) === -1 : false;
                    });
                    var bar = row.querySelector('[data-bar]');
                    bar.disabled = !pipe;
                    bar.classList.toggle('opacity-40', !pipe);
                    row.querySelector('[data-unit-label]').textContent = opt && opt.value ? opt.dataset.unit : '';
                    var sub = (parseFloat(row.querySelector('[data-qty]').value) || 0) * (parseFloat(row.querySelector('[data-price]').value) || 0);
                    row.querySelector('[data-subtotal]').textContent = rp(sub);
                    total += sub;
                });
                form.querySelector('[data-total]').textContent = rp(total);
            }

            form.addEventListener('change', function (e) {
                if (e.target.matches('[data-material]')) {
                    var row = e.target.closest('[data-row]');
                    var opt = e.target.selectedOptions[0];
                    if (opt && opt.value) {
                        if (!row.querySelector('[data-price]').value) row.querySelector('[data-price]').value = opt.dataset.price || '';
                        row.querySelector('[data-bar]').value = opt.dataset.pipe === '1' ? opt.dataset.bar : '';
                        row.querySelector('[data-warehouse]').value = opt.dataset.warehouse;
                    }
                }
                refresh();
            });
            form.addEventListener('input', refresh);
            form.addEventListener('row:added', refresh);
            form.addEventListener('row:removed', refresh);
            var addresses = @json($suppliers);
            var supplierInput = form.querySelector('#supplier');
            var locationInput = form.querySelector('[data-location]');
            supplierInput.addEventListener('change', function () {
                var match = Object.keys(addresses).find(function (name) { return name.toLowerCase() === supplierInput.value.trim().toLowerCase(); });
                if (match && addresses[match] && !locationInput.value) locationInput.value = addresses[match];
            });
            form.querySelector('[data-pay-full]').addEventListener('click', function () { form.querySelector('[data-paid]').value = Math.round(total); });
            refresh();
        })();
    </script>
@endpush
