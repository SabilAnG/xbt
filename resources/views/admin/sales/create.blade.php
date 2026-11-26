@php
    $catalog = $items->mapWithKeys(fn ($item) => [$item->id => [
        'name' => $item->name,
        'sku' => $item->sku,
        'stock' => (float) $item->stock,
        'unit' => $item->unit ?: 'pcs',
        'offline' => (float) $item->sell_price,
        'online' => (float) $item->sell_price_online,
        'cost' => (float) $item->cost_price,
    ]]);
    $channel = old('channel', 'Offline');
@endphp
@extends('layouts.admin', ['title' => 'Input penjualan'])

@section('content')
    <form method="POST" action="{{ route('admin.sales.store') }}" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]" data-sale-form>
        @csrf
        <div class="min-w-0 space-y-5">
            {{-- Produk & harga --}}
            <section class="card p-5 sm:p-6">
                <div class="mb-5 flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-[12px] bg-linear-to-br from-[#fbbf94] to-[#e9643a] text-white shadow-[0_8px_18px_-8px_#e9643a]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon('box') }}"/></svg>
                    </span>
                    <div>
                        <h2 class="card-title">Produk &amp; harga</h2>
                        <p class="text-[12px] text-muted">Qty minus untuk retur.</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-6">
                    <x-admin.field name="sold_at" label="Tanggal" type="date" :value="now()->toDateString()" required class="sm:col-span-2" />
                </div>
                @error('lines')<p class="error mt-3">{{ $message }}</p>@enderror

                <div class="mt-4 space-y-3" data-lines>
                    @foreach (old('lines', [['item_id' => '', 'qty' => '', 'unit_price' => '']]) as $i => $line)
                        <div class="sale-line grid gap-3 rounded-[14px] border border-line bg-white/60 p-3.5 sm:grid-cols-[minmax(0,1fr)_90px_150px_auto]" data-line>
                            <div class="min-w-0">
                                <label class="label">Produk</label>
                                <select name="lines[{{ $i }}][item_id]" class="input" data-item required>
                                    <option value="">— pilih knalpot —</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}" @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>{{ $item->name }} · stok {{ qty($item->stock) }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11.5px] text-muted" data-line-meta></p>
                                @error("lines.$i.item_id")<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Qty</label>
                                <input name="lines[{{ $i }}][qty]" type="number" step="any" class="input" value="{{ $line['qty'] ?? '' }}" data-qty required>
                            </div>
                            <div>
                                <label class="label">Harga satuan</label>
                                <input name="lines[{{ $i }}][unit_price]" type="number" step="any" min="0" class="input" value="{{ $line['unit_price'] ?? '' }}" data-price required>
                                <p class="mt-1 text-[11.5px] font-medium text-ink tabular-nums" data-line-total></p>
                            </div>
                            <div class="flex items-start sm:pt-6">
                                <button type="button" class="btn btn-sm" title="Hapus baris" data-remove-line>✕</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button type="button" class="btn btn-sm" data-add-line>+ Tambah produk</button>
                    <p class="text-[12px] text-muted">Harga terisi dari harga daftar sesuai saluran; ubah bila deal berbeda.</p>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <x-admin.field name="discount" label="Diskon nota (Rp)" type="number" step="any" min="0" data-discount />
                </div>
            </section>

            {{-- Saluran & pembeli --}}
            <section class="card p-5 sm:p-6">
                <div class="mb-5 flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-[12px] bg-linear-to-br from-[#9fe3f5] to-[#2fa9d6] text-white shadow-[0_8px_18px_-8px_#2fa9d6]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon('cart') }}"/></svg>
                    </span>
                    <h2 class="card-title">Saluran &amp; pembeli</h2>
                </div>
                <p class="label">Saluran</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($channels as $option)
                        <label class="cursor-pointer">
                            <input type="radio" name="channel" value="{{ $option }}" class="peer sr-only" @checked($channel === $option) data-channel>
                            <span class="inline-flex h-9 items-center rounded-[10px] border border-white bg-white px-3.5 text-[12.5px] font-medium text-ink-soft shadow-(--shadow-soft) transition peer-checked:border-transparent peer-checked:bg-linear-to-b peer-checked:from-[#f9922a] peer-checked:to-[#f2621f] peer-checked:text-white peer-checked:shadow-(--shadow-orange) peer-focus-visible:ring-3 peer-focus-visible:ring-brand-500/30 hover:text-ink">{{ $option }}</span>
                        </label>
                    @endforeach
                </div>
                @error('channel')<p class="error">{{ $message }}</p>@enderror
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <x-admin.field name="customer_name" label="Pembeli" placeholder="Nama pembeli / toko" />
                    <x-admin.field name="customer_phone" label="Telepon" placeholder="untuk resi / tracking" />
                    <x-admin.field name="notes" label="Keterangan" placeholder="mis. no. pesanan marketplace" class="sm:col-span-2" />
                </div>
            </section>

            {{-- Pembayaran --}}
            <section class="card p-5 sm:p-6">
                <div class="mb-5 flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-[12px] bg-linear-to-br from-[#c4eaa9] to-[#46ad37] text-white shadow-[0_8px_18px_-8px_#46ad37]">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon('wallet') }}"/></svg>
                    </span>
                    <div>
                        <h2 class="card-title">Pembayaran</h2>
                        <p class="text-[12px] text-muted">Kosongkan bila belum dibayar; cicilan berikutnya dicatat di halaman nota.</p>
                    </div>
                    <button type="button" class="btn btn-sm ml-auto" data-pay-full>Bayar lunas</button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field name="paid_amount" label="Uang masuk (Rp)" type="number" step="any" min="0" data-paid />
                    <x-admin.wallet-select name="wallet_id" label="Masuk ke dompet" />
                </div>
            </section>
        </div>

        {{-- Ringkasan melayang --}}
        <aside class="space-y-4 xl:sticky xl:top-28 xl:self-start">
            <section class="card p-5 sm:p-6">
                <h2 class="card-title">Ringkasan penjualan</h2>

                <div class="mt-4 flex items-center gap-3 rounded-[12px] bg-white px-3.5 py-3 shadow-[0_6px_16px_-10px_rgb(60_66_110/0.45)]">
                    <span class="grid size-10 shrink-0 place-items-center rounded-[10px] bg-accent-soft text-accent">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon('tag') }}"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-semibold text-ink" data-sum-name>Belum ada produk</p>
                        <p class="truncate text-[11.5px] text-muted" data-sum-meta>—</p>
                    </div>
                </div>

                <dl class="mt-4 space-y-2.5 text-[13px]">
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Subtotal</dt><dd class="font-medium text-ink tabular-nums" data-sum-line>—</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Diskon</dt><dd class="font-medium text-ink tabular-nums" data-sum-discount>Rp 0</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Perkiraan laba kotor</dt><dd class="font-medium tabular-nums" data-sum-profit>—</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-soft">Uang masuk</dt><dd class="font-medium text-ink tabular-nums" data-sum-paid>Rp 0</dd></div>
                </dl>

                <div class="mt-4 flex items-center gap-4 border-t border-line pt-4">
                    <span class="relative grid size-[58px] shrink-0 place-items-center">
                        <svg viewBox="0 0 60 60" class="absolute inset-0 size-full -rotate-90" aria-hidden="true">
                            <circle cx="30" cy="30" r="25" fill="none" stroke="var(--color-line)" stroke-width="7"/>
                            <circle cx="30" cy="30" r="25" fill="none" stroke="url(#ring-green)" stroke-width="7" stroke-linecap="round" pathLength="100" stroke-dasharray="0 100" data-sum-ring/>
                        </svg>
                        <span class="relative text-[11px] font-semibold text-ink" data-sum-percent>0%</span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[12px] text-ink-soft">Total</p>
                        <p class="truncate text-[24px] leading-tight font-bold tracking-tight text-ink tabular-nums" data-sum-total>Rp 0</p>
                        <p class="text-[11.5px] font-medium" data-sum-status></p>
                    </div>
                </div>
            </section>

            <div class="flex gap-2.5">
                <a href="{{ route('admin.sales.index') }}" class="btn flex-1">Batal</a>
                <button class="btn btn-primary flex-1">Simpan penjualan</button>
            </div>
        </aside>
    </form>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.querySelector('[data-sale-form]');
        const catalog = @json($catalog);
        const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
        const $ = sel => form.querySelector(sel);
        const list = $('[data-lines]'), paid = $('[data-paid]'), discount = $('[data-discount]');
        const template = list.querySelector('[data-line]').cloneNode(true);

        const channel = () => (form.querySelector('[data-channel]:checked') || {}).value || 'Offline';
        const listPrice = data => channel() === 'Offline' ? data.offline : (data.online || data.offline);
        const lines = () => [...list.querySelectorAll('[data-line]')];

        function renumber() {
            lines().forEach((row, i) => row.querySelectorAll('[name^="lines["]').forEach(input => {
                input.name = input.name.replace(/^lines\[\d+\]/, 'lines[' + i + ']');
            }));
            list.querySelectorAll('[data-remove-line]').forEach(button => button.disabled = lines().length === 1);
        }

        function totals() {
            let subtotal = 0, cost = 0, units = 0;
            const used = {};
            lines().forEach(row => {
                const data = catalog[row.querySelector('[data-item]').value];
                const q = parseFloat(row.querySelector('[data-qty]').value) || 0;
                const p = parseFloat(row.querySelector('[data-price]').value) || 0;
                subtotal += q * p;
                if (data) { cost += q * data.cost; units += q; used[row.querySelector('[data-item]').value] = (used[row.querySelector('[data-item]').value] || 0) + q; }
            });
            return { subtotal, cost, units, used };
        }

        function refresh() {
            const { subtotal, cost, units, used } = totals();
            const disc = parseFloat(discount.value) || 0;
            const total = subtotal - disc;
            const cash = parseFloat(paid.value) || 0;

            lines().forEach(row => {
                const id = row.querySelector('[data-item]').value;
                const data = catalog[id];
                const q = parseFloat(row.querySelector('[data-qty]').value) || 0;
                const p = parseFloat(row.querySelector('[data-price]').value) || 0;
                const meta = row.querySelector('[data-line-meta]');
                meta.textContent = data ? data.sku + ' · stok ' + data.stock.toLocaleString('id-ID') + ' ' + data.unit + ' · offline ' + rp(data.offline) + ' · online ' + rp(data.online) : '';
                meta.classList.toggle('text-rose-600', !!data && used[id] > data.stock);
                row.querySelector('[data-line-total]').textContent = q && p ? rp(q * p) : '';
            });

            const filled = lines().filter(row => catalog[row.querySelector('[data-item]').value]);
            $('[data-sum-name]').textContent = filled.length ? filled.length + ' produk · ' + units.toLocaleString('id-ID') + ' unit' : 'Belum ada produk';
            $('[data-sum-meta]').textContent = filled.map(row => catalog[row.querySelector('[data-item]').value].name).join(', ') || '—';
            $('[data-sum-line]').textContent = rp(subtotal);
            $('[data-sum-discount]').textContent = disc ? '−' + rp(disc) : 'Rp 0';
            const profit = filled.length ? total - cost : null;
            const profitEl = $('[data-sum-profit]');
            profitEl.textContent = profit === null ? '—' : rp(profit);
            profitEl.className = 'font-medium tabular-nums ' + (profit === null ? 'text-ink' : profit >= 0 ? 'text-emerald-600' : 'text-rose-600');
            $('[data-sum-paid]').textContent = rp(cash);
            $('[data-sum-total]').textContent = rp(total);

            const share = total > 0 ? Math.min(cash / total * 100, 100) : 0;
            $('[data-sum-ring]').setAttribute('stroke-dasharray', Math.max(share, total > 0 && cash > 0 ? 4 : 0) + ' 100');
            $('[data-sum-percent]').textContent = Math.round(share) + '%';
            const status = $('[data-sum-status]');
            if (total <= 0) { status.textContent = ''; }
            else if (cash >= total) { status.textContent = 'Lunas'; status.className = 'text-[11.5px] font-medium text-emerald-600'; }
            else { status.textContent = 'Piutang ' + rp(total - cash); status.className = 'text-[11.5px] font-medium text-rose-600'; }
        }

        // Harga daftar terisi otomatis sesuai saluran, selama belum diubah manual.
        function autoPrice(row) {
            const data = catalog[row.querySelector('[data-item]').value];
            const price = row.querySelector('[data-price]'), qty = row.querySelector('[data-qty]');
            if (!data) return;
            const suggested = listPrice(data);
            if (price.value === '' || price.dataset.auto === price.value) {
                price.value = suggested || '';
                price.dataset.auto = price.value;
            }
            if (qty.value === '') qty.value = 1;
        }

        list.addEventListener('change', e => {
            const row = e.target.closest('[data-line]');
            if (row && e.target.matches('[data-item]')) autoPrice(row);
            refresh();
        });
        list.addEventListener('click', e => {
            const button = e.target.closest('[data-remove-line]');
            if (!button || lines().length === 1) return;
            button.closest('[data-line]').remove();
            renumber();
            refresh();
        });
        $('[data-add-line]').addEventListener('click', () => {
            const row = template.cloneNode(true);
            row.querySelectorAll('input').forEach(input => { input.value = ''; delete input.dataset.auto; });
            row.querySelectorAll('select').forEach(select => select.value = '');
            // Tombol dropdown hasil salinan dibuang; dropdown baru dibuat otomatis.
            row.querySelectorAll('.error, .cselect-button').forEach(el => el.remove());
            row.querySelectorAll('select').forEach(select => { delete select.dataset.cselect; select.classList.remove('cselect-native'); select.removeAttribute('tabindex'); });
            row.querySelector('[data-line-meta]').textContent = '';
            list.append(row);
            renumber();
            row.querySelector('[data-item]').focus();
            refresh();
        });
        form.querySelectorAll('[data-channel]').forEach(radio => radio.addEventListener('change', () => { lines().forEach(autoPrice); refresh(); }));
        $('[data-pay-full]').addEventListener('click', () => {
            const { subtotal } = totals();
            paid.value = Math.round(subtotal - (parseFloat(discount.value) || 0)) || '';
            refresh();
        });
        form.addEventListener('input', refresh);
        renumber();
        refresh();
    })();
</script>
@endpush
