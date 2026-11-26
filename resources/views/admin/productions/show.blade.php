@extends('layouts.admin', ['title' => 'Produksi '.$production->production_number, 'subtitle' => $production->formula->name.' × '.qty($production->qty).' · '.$production->produced_at->translatedFormat('d M Y').($production->isVendor() ? ' · vendor '.$production->vendor.($production->process ? ' ('.$production->process.')' : '') : '').($production->worker ? ' · '.$production->worker : '')])

@section('actions')
    @if ($production->isPosted())
        <form method="POST" action="{{ route('admin.productions.cancel', $production) }}" onsubmit="return confirm('Batalkan pembukuan? Stok bahan, potongan pipa, dan stok hasilnya akan dikembalikan.')">
            @csrf<button class="btn btn-danger">Batalkan pembukuan</button>
        </form>
    @else
        <a href="{{ route('admin.productions.edit', $production) }}" class="btn">Ubah</a>
        <form method="POST" action="{{ route('admin.productions.post', $production) }}">
            @csrf<button class="btn btn-primary" @disabled(! $preview['ready'])>{{ $production->isVendor() ? 'Kirim ke vendor' : 'Bukukan produksi' }}</button>
        </form>
    @endif
@endsection

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
