@extends('layouts.admin', ['title' => 'Produksi '.$production->production_number, 'subtitle' => $production->formula->name.' × '.qty($production->qty).' · '.$production->produced_at->translatedFormat('d M Y').($production->isVendor() ? ' · vendor '.$production->vendor.($production->process ? ' ('.$production->process.')' : '') : '').($production->worker ? ' · '.$production->worker : '')])

@section('actions')
    @if ($production->isPosted())
        <form method="POST" action="{{ route('admin.productions.cancel', $production) }}" data-confirm="Batalkan pembukuan? Stok bahan, potongan pipa, dan stok hasilnya akan dikembalikan.">
            @csrf<button class="btn btn-danger">Batalkan pembukuan</button>
        </form>
    @else
        <a href="{{ route('admin.productions.edit', $production) }}" class="btn">Ubah</a>
        <form method="POST" action="{{ route('admin.productions.post', $production) }}" id="post-form">
            @csrf<button class="btn btn-primary" @disabled(! $preview['ready'])>{{ $production->isVendor() ? 'Kirim ke vendor' : 'Bukukan produksi' }}</button>
        </form>
    @endif
@endsection

@push('scripts')
<script>
    (() => {
        const box = document.querySelector('[data-leftovers]');
        if (!box) return;
        const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
        const total = box.querySelector('[data-waste-total]');
        const refresh = () => {
            let waste = 0;
            box.querySelectorAll('[data-leftover-row]').forEach(row => {
                if (row.querySelector('[data-leftover-choice][value="sampah"]').checked) waste += parseFloat(row.dataset.value) || 0;
            });
            total.textContent = rp(waste);
        };
        box.addEventListener('change', refresh);
        box.querySelectorAll('[data-leftover-all]').forEach(button => button.addEventListener('click', () => {
            box.querySelectorAll(`[data-leftover-choice][value="${button.dataset.leftoverAll}"]`).forEach(radio => { radio.checked = true; });
            refresh();
        }));
    })();
</script>
@endpush

@section('content')
    @if ($production->isPosted())
        <section class="stat-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
            <x-admin.stat :label="$production->isVendor() ? 'Bahan / unit (belum termasuk jasa)' : 'HPP / unit'" :value="rupiah($production->hpp_per_unit)" tone="brand" />
            <x-admin.stat label="Bahan" :value="rupiah($production->material_cost)" />
            <x-admin.stat label="Sisa afkir" :value="rupiah($production->waste_cost)" :tone="(float) $production->waste_cost > 0 ? 'warning' : 'default'" />
            <x-admin.stat label="Ongkos" :value="rupiah($production->other_cost)" />
            @if ($production->isVendor())
                <x-admin.stat label="Masih di vendor" :value="qty($production->outstandingQty()).' / '.qty($production->qty)" :hint="$production->vendor" :tone="$production->outstandingQty() > 0 ? 'warning' : 'success'" />
            @elseif ($production->formula->isSemiFinished())
                <x-admin.stat label="Masuk Setengah Jadi" :value="qty($production->qty).' pcs'" :hint="$production->formula->outputMaterial?->label()" :href="route('admin.materials.show', $production->formula->outputMaterial)" tone="success" />
            @else
                <x-admin.stat label="Masuk Finish Good" :value="qty($production->qty).' pcs'" :hint="$production->item->name" :href="route('admin.items.show', $production->item)" tone="success" />
            @endif
        </section>

        @if ($production->isVendor())
            <section class="grid gap-4 sm:gap-6 lg:grid-cols-2">
                <div class="card overflow-hidden">
                    <div class="card-header">
                        <h2 class="card-title">Penerimaan dari vendor</h2>
                        <span class="text-xs text-slate-500">{{ $production->formula->outputMaterial?->label() }}</span>
                    </div>
                    <table class="table">
                        <thead><tr><th>Tanggal</th><th class="num">Jumlah</th><th class="num">Jasa / unit</th><th class="num">HPP / unit</th><th>Tagihan</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($production->receipts as $receipt)
                            <tr>
                                <td class="whitespace-nowrap">{{ $receipt->received_at->translatedFormat('d M Y') }}</td>
                                <td class="num font-semibold">{{ qty($receipt->qty) }}</td>
                                <td class="num">{{ rupiah($receipt->service_cost_per_unit) }}</td>
                                <td class="num">{{ rupiah($receipt->unit_cost) }}</td>
                                <td>
                                    @if ($receipt->bill)
                                        <a href="{{ route('admin.purchases.show', $receipt->bill) }}" class="link font-mono text-xs">{{ $receipt->bill->invoice_number }}</a>
                                        <x-admin.status :value="$receipt->bill->paymentStatus()" />
                                    @else
                                        <span class="text-xs text-slate-400">tanpa jasa</span>
                                    @endif
                                </td>
                                <td class="text-right"><x-admin.delete-button :action="route('admin.productions.receipts.destroy', $receipt)" label="✕" confirm="Hapus penerimaan ini? Stok hasil dan tagihan jasanya ikut dibatalkan." /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-slate-500">Belum ada yang diterima.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($production->outstandingQty() > 0)
                    <form method="POST" action="{{ route('admin.productions.receive', $production) }}" class="card space-y-4 p-6">
                        @csrf
                        <h2 class="card-title">Terima hasil dari {{ $production->vendor }}</h2>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-admin.field name="received_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                            <x-admin.field name="qty" label="Jumlah diterima" type="number" min="1" step="1" :value="(int) $production->outstandingQty()" required />
                            <x-admin.field name="service_cost_per_unit" label="Jasa per unit (Rp)" type="number" step="any" min="0" :value="(float) $production->service_cost_per_unit" required />
                            <x-admin.field name="notes" label="Catatan" class="sm:col-span-3" placeholder="mis. no. nota vendor" />
                        </div>
                        <p class="text-xs text-slate-500">Hasil masuk Gudang Setengah Jadi dengan HPP = bahan {{ rupiah($production->hpp_per_unit) }} + jasa. Jasa × jumlah dicatat sebagai hutang ke {{ $production->vendor }} di Pembelian Bahan.</p>
                        <div class="flex justify-end"><button class="btn btn-primary">Simpan penerimaan</button></div>
                    </form>
                @endif
            </section>
        @endif

        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Bahan terpakai</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Bagian · komponen</th><th>Bahan</th><th class="num">Jumlah</th><th class="num">Harga</th><th class="num">Biaya</th></tr></thead>
                    <tbody>
                    @foreach ($production->usages as $usage)
                        <tr>
                            <td>{{ $usage->part_name }} · {{ $usage->component_name }}</td>
                            <td><a href="{{ route('admin.materials.show', $usage->material) }}" class="link">{{ $usage->material->label() }}</a></td>
                            <td class="num">{{ qty($usage->qty, 2) }} {{ $usage->material->stockUnit() }}</td>
                            <td class="num text-slate-500">{{ rupiah($usage->unit_cost) }}</td>
                            <td class="num font-semibold">{{ rupiah($usage->total_cost) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($production->serviceBills->isNotEmpty())
            <section class="card p-5 sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-[12px] bg-linear-to-br from-[#dccafd] to-[#8b49dc] text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon('truck') }}"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="card-title">Jasa vendor komponen</h2>
                        <p class="text-[12px] text-muted">Total {{ rupiah($production->service_cost) }} · tercatat sebagai hutang di Pembelian</p>
                    </div>
                </div>
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($production->serviceBills as $bill)
                        <a href="{{ route('admin.purchases.show', $bill) }}" class="flex items-center gap-3 rounded-[12px] bg-white/80 px-3.5 py-2.5 shadow-[0_4px_12px_-10px_rgb(60_66_110/0.5)] transition hover:-translate-y-px">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] font-semibold text-ink">{{ $bill->supplier }} · {{ rupiah($bill->total) }}</p>
                                <p class="truncate text-[11.5px] text-muted">{{ $bill->invoice_number }} · {{ $bill->notes }}</p>
                            </div>
                            <x-admin.status :value="$bill->paymentStatus()" />
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @php
            $leftPieces = $production->cuts->pluck('piece')->unique('id')->filter(fn ($piece) => (float) $piece->length_cm > 0 && in_array($piece->status, ['tersedia', 'afkir'], true));
        @endphp
        @if ($leftPieces->isNotEmpty())
            <section class="card p-5 sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-[12px] bg-linear-to-br from-[#c4eaa9] to-[#46ad37] text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon('puzzle') }}"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="card-title">Sisa produksi</h2>
                        <p class="text-[12px] text-muted">{{ $leftPieces->where('status', 'tersedia')->count() }} kembali ke stok · {{ $leftPieces->where('status', 'afkir')->count() }} dibuang</p>
                    </div>
                    <a href="{{ route('admin.warehouses.show', 'stok-sisa') }}" class="btn btn-sm">Gudang Stok Sisa</a>
                </div>
                <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($leftPieces as $piece)
                        <div class="flex items-center gap-3 rounded-[12px] bg-white/80 px-3.5 py-2.5 shadow-[0_4px_12px_-10px_rgb(60_66_110/0.5)]">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] font-semibold text-ink">{{ qty($piece->length_cm, 2) }} cm · <span class="font-mono text-[11px] font-normal text-muted">{{ $piece->code }}</span></p>
                                <p class="truncate text-[11.5px] text-muted">{{ $piece->material->label() }} · {{ rupiah($piece->value()) }}</p>
                            </div>
                            @if ($piece->status === 'tersedia')
                                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">Kembali ke stok</span>
                            @else
                                <span class="shrink-0 rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-600">Dibuang</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($production->cuts->isNotEmpty())
            <section class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Potongan pipa</h2></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>#</th><th>Untuk</th><th>Pipa</th><th>Potongan</th><th class="num">Panjang</th><th class="num">Sebelum</th><th class="num">Biaya</th><th>Keadaan potongan sekarang</th></tr></thead>
                        <tbody>
                        @foreach ($production->cuts as $cut)
                            <tr>
                                <td class="text-xs text-slate-400">{{ $cut->sequence }}</td>
                                <td>{{ $cut->label }}</td>
                                <td class="text-sm">{{ $cut->piece->material->label() }}</td>
                                <td class="font-mono text-xs">{{ $cut->piece->code }}</td>
                                <td class="num font-semibold">{{ qty($cut->length_cm, 2) }} cm</td>
                                <td class="num text-slate-500">{{ qty($cut->before_length_cm, 2) }} cm</td>
                                <td class="num">{{ rupiah($cut->cost) }}</td>
                                <td class="text-xs">
                                    {{ qty($cut->piece->length_cm, 2) }} cm · {{ $cut->piece->status === 'tersedia' ? $cut->piece->warehouse->typeLabel() : $cut->piece->status }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @else
        @unless ($preview['ready'])
            <div class="card border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Belum bisa dibukukan — stok kurang:</p>
                <ul class="mt-1 list-disc pl-5">@foreach ($preview['problems'] as $problem)<li>{{ $problem }}</li>@endforeach</ul>
                <p class="mt-2"><a href="{{ route('admin.purchases.create') }}" class="link">Catat pembelian bahan</a> atau <a href="{{ route('admin.adjustments.create') }}" class="link">isi stok awal</a> dulu.</p>
            </div>
        @endunless

        <section class="stat-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.stat label="Perkiraan HPP / knalpot" :value="rupiah($preview['hpp'])" tone="brand" />
            <x-admin.stat label="Bahan" :value="rupiah($preview['material_cost'])" />
            <x-admin.stat label="Sisa afkir" :value="rupiah($preview['waste_cost'])" />
            <x-admin.stat label="Ongkos" :value="rupiah($preview['other_cost'])" />
        </section>

        @if ($preview['services']->isNotEmpty())
            <section class="card p-5 sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-[12px] bg-linear-to-br from-[#dccafd] to-[#8b49dc] text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon('truck') }}"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="card-title">Komponen dijasakan</h2>
                        <p class="text-[12px] text-muted">Total jasa {{ rupiah($preview['service_cost']) }} · jadi tagihan ke vendor saat dibukukan</p>
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Komponen</th><th>Vendor</th><th>Proses</th><th class="num">Jumlah</th><th class="num">Tarif</th><th class="num">Jasa</th></tr></thead>
                        <tbody>
                        @foreach ($preview['services'] as $service)
                            <tr>
                                <td>{{ $service['part'] }} · {{ $service['component'] }}</td>
                                <td>{{ $service['vendor'] }}</td>
                                <td class="text-muted">{{ $service['process'] ?? '—' }}</td>
                                <td class="num">{{ qty($service['qty']) }} buah</td>
                                <td class="num">{{ rupiah($service['rate']) }}</td>
                                <td class="num font-semibold">{{ rupiah($service['cost']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Bahan per satuan yang akan dipakai</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Bahan</th><th>Gudang</th><th>Untuk</th><th class="num">Butuh</th><th class="num">Stok</th><th class="num">Biaya</th></tr></thead>
                    <tbody>
                    @forelse ($preview['units'] as $row)
                        <tr>
                            <td>{{ $row['material']->label() }}</td>
                            <td class="text-xs">{{ $row['material']->warehouse->typeLabel() }}</td>
                            <td class="text-xs text-slate-500">{{ $row['lines']->map(fn ($line) => $line['part'].' · '.$line['component'])->implode(', ') }}</td>
                            <td class="num font-semibold">{{ qty($row['need']) }} {{ $row['material']->stockUnit() }}</td>
                            <td class="num {{ $row['available'] + 0.0001 < $row['need'] ? 'text-rose-600 font-semibold' : 'text-slate-500' }}">{{ qty($row['available']) }}</td>
                            <td class="num">{{ rupiah($row['cost']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-slate-500">Tidak ada bahan per satuan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @php $leftovers = $preview['pipes']->flatMap(fn ($pipe) => collect($pipe['outcomes'])->where('after', '>', 0))->values(); @endphp
        @if ($leftovers->isNotEmpty())
            <section class="card p-5 sm:p-6" data-leftovers>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-[12px] bg-linear-to-br from-[#c4eaa9] to-[#46ad37] text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon('puzzle') }}"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="card-title">Sisa produksi</h2>
                        <p class="text-[12px] text-muted">{{ $leftovers->count() }} potongan sisa · total {{ qty($leftovers->sum('after'), 2) }} cm · nilai {{ rupiah($leftovers->sum('value')) }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-sm" data-leftover-all="stok">Semua ke stok</button>
                        <button type="button" class="btn btn-sm" data-leftover-all="sampah">Semua dibuang</button>
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Potongan</th><th>Bahan</th><th class="num">Panjang awal</th><th class="num">Sisa</th><th class="num">Nilai</th><th>Ke mana</th></tr></thead>
                        <tbody>
                        @foreach ($leftovers as $left)
                            <tr data-leftover-row data-value="{{ $left['value'] }}">
                                <td class="font-mono text-xs">{{ $left['code'] }}</td>
                                <td class="text-[13px]">{{ $left['material'] }}</td>
                                <td class="num text-muted">{{ qty($left['before'], 2) }} cm</td>
                                <td class="num font-semibold text-ink">{{ qty($left['after'], 2) }} cm</td>
                                <td class="num">{{ rupiah($left['value']) }}</td>
                                <td>
                                    <div class="inline-flex rounded-full bg-surface p-1 shadow-[inset_0_1px_3px_rgb(60_66_110/0.15)]">
                                        @foreach (['stok' => ['Kembali ke stok', 'sisa'], 'sampah' => ['Buang ke sampah', 'afkir']] as $choice => [$choiceLabel, $status])
                                            <label class="cursor-pointer">
                                                <input type="radio" form="post-form" name="leftover[{{ $left['key'] }}]" value="{{ $choice }}" class="peer sr-only" data-leftover-choice @checked($left['suggested'] === $status)>
                                                <span @class([
                                                    'inline-flex h-8 items-center gap-1.5 rounded-full px-3 text-[12px] font-medium text-ink-soft transition hover:text-ink',
                                                    'peer-checked:bg-linear-to-b peer-checked:from-[#4fd08a] peer-checked:to-[#1fae5b] peer-checked:text-white peer-checked:shadow-[0_6px_14px_-6px_#1fae5b]' => $choice === 'stok',
                                                    'peer-checked:bg-linear-to-b peer-checked:from-[#fb7185] peer-checked:to-[#e11d48] peer-checked:text-white peer-checked:shadow-[0_6px_14px_-6px_#e11d48]' => $choice === 'sampah',
                                                ])>
                                                    @if ($choice === 'stok')
                                                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 21V9l9-6 9 6v12M7 21v-8h10v8"/></svg>
                                                    @else
                                                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 7h16m-10 4v6m4-6v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
                                                    @endif
                                                    {{ $choiceLabel }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @if ($left['suggested'] === 'afkir')
                                        <p class="mt-1 text-[11px] text-muted">Lebih pendek dari batas sisa minimal</p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-[12px] text-ink-soft">Kembali ke stok → masuk <strong>Gudang Stok Sisa Produksi</strong> dan bisa dipakai produksi berikutnya. Dibuang → nilainya dihitung sebagai biaya afkir (<strong data-waste-total>{{ rupiah($preview['waste_cost']) }}</strong>).</p>
            </section>
        @endif

        @foreach ($preview['pipes'] as $pipe)
            <section class="card overflow-hidden">
                <div class="card-header">
                    <h2 class="card-title">Rencana potong — {{ $pipe['material']->label() }}</h2>
                    <span class="text-xs text-slate-500">{{ qty($pipe['need'], 2) }} cm · biaya {{ rupiah($pipe['cost']) }}</span>
                </div>
                <div class="px-5 pb-4">
                    @include('admin.productions.partials.cut-table', ['assignments' => $pipe['assignments'], 'material' => $pipe['material']])
                    @if ($pipe['outcomes'])
                        <p class="mt-3 text-xs font-semibold text-slate-600">Setelah dipotong:</p>
                        <ul class="mt-1 flex flex-wrap gap-2 text-xs">
                            @foreach ($pipe['outcomes'] as $outcome)
                                <li class="rounded-lg border border-slate-200 px-2 py-1">
                                    <span class="font-mono">{{ $outcome['code'] }}</span> {{ qty($outcome['before'], 2) }} → {{ qty($outcome['after'], 2) }} cm
                                    <x-admin.status :value="['sisa' => 'SISA', 'afkir' => 'AFKIR', 'habis' => 'HABIS TERPAKAI'][$outcome['status']]" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @foreach ($pipe['unplaced'] as $cut)
                        <p class="mt-2 text-xs text-rose-600">Belum ada pipa untuk {{ $cut['label'] }} {{ qty($cut['length'], 2) }} cm.</p>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="border-t border-slate-200 pt-4">
            <x-admin.delete-button :action="route('admin.productions.destroy', $production)" label="Hapus draf" />
        </div>
    @endif
@endsection
