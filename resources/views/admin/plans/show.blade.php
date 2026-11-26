@extends('layouts.admin', ['title' => 'Rencana '.$plan->plan_number, 'subtitle' => ($plan->title ? $plan->title.' · ' : '').$plan->planned_for->translatedFormat('d M Y').' · '.$plan->statusLabel()])

@section('actions')
    @if ($plan->productions()->doesntExist())
        <a href="{{ route('admin.plans.edit', $plan) }}" class="btn">Ubah</a>
    @endif
    @if ($needs['buy_total'] > 0)
        <a href="{{ route('admin.purchases.create', ['rencana' => $plan->id]) }}" class="btn">Beli kekurangan</a>
    @endif
    <form method="POST" action="{{ route('admin.plans.productions', $plan) }}">@csrf<button class="btn btn-primary">Mulai produksi</button></form>
@endsection

@section('content')
    @php $rows = $needs['rows']; $shortRows = $rows->filter(fn ($row) => $row['buy_qty'] > 0); @endphp

    <section class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-admin.stat label="Total unit" :value="qty($plan->totalUnits()).' knalpot'" />
        <x-admin.stat label="Perkiraan biaya produksi" :value="rupiah($needs['estimated_cost'])" />
        <x-admin.stat label="Bahan kurang" :value="$shortRows->count().' jenis'" :tone="$shortRows->isEmpty() ? 'success' : 'warning'" />
        <x-admin.stat label="Perkiraan belanja" :value="rupiah($needs['buy_total'])" :tone="$needs['buy_total'] > 0 ? 'warning' : 'success'" />
    </section>

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Formula yang dibuat</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Formula</th><th class="num">Unit</th><th class="num">HPP perkiraan</th><th class="num">Total</th><th>Nota produksi</th></tr></thead>
                <tbody>
                @foreach ($plan->lines as $line)
                    @php $info = $needs['lines'][$loop->index]; @endphp
                    <tr>
                        <td><a href="{{ route('admin.formulas.show', $line->formula) }}" class="link">{{ $line->formula->name }}</a></td>
                        <td class="num font-semibold">{{ qty($line->qty) }}</td>
                        <td class="num">{{ rupiah($info['hpp']) }}</td>
                        <td class="num">{{ rupiah($info['cost']) }}</td>
                        <td class="text-sm">
                            @forelse ($line->productions as $production)
                                <a href="{{ route('admin.productions.show', $production) }}" class="link font-mono text-xs">{{ $production->production_number }}</a>
                                <x-admin.status :value="$production->statusLabel()" />
                            @empty
                                <span class="text-slate-400">Belum dibuat</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Kebutuhan bahan</h2>
            <span class="text-xs text-slate-500">Pipa: potongan sisa dipakai lebih dulu, lalu batang utuh.</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Bahan</th><th>Gudang</th><th>Dipakai untuk</th><th class="num">Butuh</th><th class="num">Stok</th><th class="num">Perlu beli</th><th class="num">Perkiraan belanja</th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    @php $material = $row['material']; @endphp
                    <tr>
                        <td class="min-w-44"><a href="{{ route('admin.materials.show', $material) }}" class="link">{{ $material->label() }}</a></td>
                        <td class="text-xs whitespace-nowrap">{{ $material->warehouse->typeLabel() }}</td>
                        <td class="min-w-48 text-xs text-slate-500">{{ implode(' · ', $row['uses']) }}</td>
                        <td class="num whitespace-nowrap">{{ qty($row['need'], 2) }} {{ $material->stockUnit() }}</td>
                        <td class="num whitespace-nowrap text-slate-500">{{ qty($row['available'], 2) }} {{ $material->stockUnit() }}</td>
                        <td class="num font-semibold whitespace-nowrap {{ $row['buy_qty'] > 0 || $row['make_qty'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                            @if ($row['made_by'])
                                @if ($row['make_qty'] > 0)
                                    Perlu dibuat {{ qty($row['make_qty']) }}
                                @else
                                    Cukup
                                @endif
                                <p class="text-xs font-normal text-slate-500">dibuat lewat formula {{ $row['made_by'] }}{{ $row['planned'] > 0 ? ' · direncanakan '.qty($row['planned']) : '' }}</p>
                            @else
                                {{ $row['buy_qty'] > 0 ? qty($row['buy_qty']).' '.$row['buy_unit'] : 'Cukup' }}
                                @if ($row['on_order'] > 0)
                                    <p class="text-xs font-normal text-amber-600">sudah dipesan {{ qty($row['on_order'], 2) }} {{ $material->stockUnit() }}, belum diterima</p>
                                @endif
                            @endif
                        </td>
                        <td class="num">{{ $row['buy_cost'] > 0 ? rupiah($row['buy_cost']) : '—' }}</td>
                    </tr>
                    @if ($row['cutting'])
                        <tr class="bg-slate-50/60">
                            <td colspan="7" class="py-2">
                                <details>
                                    <summary class="cursor-pointer text-xs font-medium text-slate-600">Rencana potong {{ count($row['cutting']['assignments']) }} potongan
                                        @if ($row['cutting']['new_bars']) — butuh {{ $row['cutting']['new_bars'] }} batang baru @endif
                                    </summary>
                                    @include('admin.productions.partials.cut-table', ['assignments' => $row['cutting']['assignments'], 'material' => $material])
                                    @foreach ($row['cutting']['unplaced'] as $cut)
                                        <p class="mt-1 text-xs text-rose-600">{{ $cut['label'] }} {{ qty($cut['length'], 2) }} cm lebih panjang dari 1 batang ({{ qty($material->bar_length_cm, 2) }} cm).</p>
                                    @endforeach
                                </details>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">Formula di rencana ini belum punya bahan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($plan->purchases->isNotEmpty())
        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Pembelian untuk rencana ini</h2></div>
            <table class="table">
                <tbody>
                @foreach ($plan->purchases as $purchase)
                    <tr>
                        <td><a href="{{ route('admin.purchases.show', $purchase) }}" class="link font-mono text-xs">{{ $purchase->invoice_number }}</a></td>
                        <td>{{ $purchase->purchased_at->translatedFormat('d M Y') }} · {{ $purchase->supplier ?? '—' }}</td>
                        <td class="num">{{ rupiah($purchase->total) }}</td>
                        <td><x-admin.status :value="$purchase->paymentStatus()" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if ($plan->productions()->doesntExist())
        <div class="border-t border-slate-200 pt-4">
            <x-admin.delete-button :action="route('admin.plans.destroy', $plan)" label="Hapus rencana" />
        </div>
    @endif
@endsection
