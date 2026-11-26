@php
    use App\Models\Warehouse;
    use App\Support\AdminNavigation;

    $total = (float) $cards->sum('value');
    // Warna & ikon per gudang, senada dengan cincin statistik dashboard.
    $styles = [
        Warehouse::RAW => ['from-[#fbbf94] to-[#e9643a]', '#ee7a45', 'warehouse'],
        Warehouse::SEMI => ['from-[#9fe3f5] to-[#2fa9d6]', '#3fbbe2', 'layers'],
        Warehouse::ACCESSORY => ['from-[#fde39a] to-[#eba50f]', '#eeaa12', 'tag'],
        Warehouse::OFFCUT => ['from-[#c4eaa9] to-[#46ad37]', '#4fb83f', 'puzzle'],
        Warehouse::VENDOR => ['from-[#dccafd] to-[#8b49dc]', '#9553e0', 'truck'],
        Warehouse::FINISHED => ['from-[#fdbac4] to-[#df3f58]', '#e5465c', 'box'],
    ];
    $byType = $cards->keyBy(fn ($card) => $card['warehouse']->type);
    $storageValue = (float) $cards->filter(fn ($card) => in_array($card['warehouse']->type, [Warehouse::RAW, Warehouse::SEMI, Warehouse::ACCESSORY, Warehouse::OFFCUT], true))->sum('value');
    $short = fn (float $n) => 'Rp '.match (true) {
        $n >= 1_000_000_000 => qty($n / 1_000_000_000, 1).' M',
        $n >= 1_000_000 => qty($n / 1_000_000, 1).' jt',
        $n >= 1_000 => qty($n / 1_000, 0).' rb',
        default => qty($n, 0),
    };
    $share = fn (float $value) => $total > 0 ? $value / $total * 100 : 0;
@endphp
@extends('layouts.admin', ['title' => 'Gudang'])

@section('actions')
    <a href="{{ route('admin.transfers.create') }}" class="btn">Pindah barang</a>
    <a href="{{ route('admin.adjustments.create') }}" class="btn">Penyesuaian stok</a>
    <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary">+ Pembelian</a>
@endsection

@section('content')
    {{-- Ringkasan bercincin --}}
    <section class="stat-grid grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Total persediaan" :value="$short($total)" :title="rupiah($total)" :percent="$total > 0 ? 100 : 6" hint="semua gudang" />
        <x-admin.stat label="Bahan di gudang" :value="$short($storageValue)" :title="rupiah($storageValue)" :percent="$share($storageValue)"
            :hint="($byType[Warehouse::RAW]['count'] + $byType[Warehouse::SEMI]['count'] + $byType[Warehouse::ACCESSORY]['count']).' jenis bahan'" />
        <x-admin.stat label="Potongan sisa pipa" :value="qty($byType[Warehouse::OFFCUT]['qty'] ?? 0).' pcs'" :percent="$share($byType[Warehouse::OFFCUT]['value'])"
            :hint="rupiah($byType[Warehouse::OFFCUT]['value'])" :href="route('admin.warehouses.show', 'stok-sisa')" />
        <x-admin.stat label="Sedang di vendor" :value="qty($byType[Warehouse::VENDOR]['qty'] ?? 0).' unit'" trend="down" :percent="$share($byType[Warehouse::VENDOR]['value'])"
            :hint="$byType[Warehouse::VENDOR]['count'].' pekerjaan'" :href="route('admin.warehouses.show', 'di-vendor')" />
    </section>

    {{-- Kartu gudang --}}
    <section class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as $card)
            @php
                $warehouse = $card['warehouse'];
                [$gradient, $color, $icon] = $styles[$warehouse->type] ?? $styles[Warehouse::RAW];
                $percent = $share($card['value']);
                $detail = $card['count'].' '.$card['unit'];
                if ($card['qty'] !== null) {
                    $detail .= ' · '.qty($card['qty']).' '.(['finish_good' => 'pcs', 'di_vendor' => 'unit di vendor'][$warehouse->type] ?? 'potongan');
                }
            @endphp
            <a href="{{ route('admin.warehouses.show', $warehouse->slug()) }}" class="card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-(--shadow-pop) sm:p-6">
                <div class="flex items-start gap-4">
                    <span class="grid size-[52px] shrink-0 place-items-center rounded-[14px] bg-linear-to-br {{ $gradient }} text-white ring-4 ring-white" style="box-shadow: 0 10px 22px -10px {{ $color }}">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ AdminNavigation::icon($icon) }}"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <span class="inline-flex rounded-md bg-white px-2 py-0.5 text-[10.5px] font-semibold tracking-wider text-muted shadow-[0_2px_6px_-3px_rgb(60_66_110/0.35)]">{{ $warehouse->code }}</span>
                        <h2 class="mt-1.5 text-[15px] leading-snug font-semibold text-ink">{{ $warehouse->name }}</h2>
                    </div>
                    <span class="tile size-9 group-hover:text-brand-600">
                        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 17 17 7m0 0H9m8 0v8"/></svg>
                    </span>
                </div>

                <p class="mt-3 line-clamp-2 min-h-[2.4rem] text-[12px] leading-snug text-ink-soft">{{ $warehouse->typeDescription() }}</p>

                <div class="mt-4 flex items-end justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-[24px] leading-tight font-bold tracking-tight text-ink tabular-nums" title="{{ rupiah($card['value']) }}">{{ $short($card['value']) }}</p>
                        <p class="mt-0.5 truncate text-[12px] text-muted">{{ $detail }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-white px-2.5 py-1 text-[11.5px] font-semibold tabular-nums shadow-[0_4px_10px_-6px_rgb(60_66_110/0.45)]" style="color: {{ $color }}">{{ qty($percent, 0) }}%</span>
                </div>
                <div class="mt-3 h-[5px] overflow-hidden rounded-full bg-line">
                    <div class="h-full rounded-full bg-linear-to-r {{ $gradient }}" style="width: {{ max(round($percent, 2), $card['value'] > 0 ? 3 : 0) }}%"></div>
                </div>
            </a>
        @endforeach
    </section>

    {{-- Pergerakan terakhir --}}
    <section class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 px-5 pt-5 pb-3 sm:px-6">
            <span class="pill h-11 text-[14px]">Pergerakan terakhir</span>
            <span class="text-[12px] text-muted">15 catatan terbaru dari kartu stok</span>
            <a href="{{ route('admin.adjustments.index') }}" class="tile ml-auto" title="Buka penyesuaian stok">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 4h6v6m0-6L10 14m-1-9H5a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1v-4"/></svg>
            </a>
        </div>
        <div class="scrollbar-thin overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Gudang</th><th>Bahan</th><th>Jenis</th><th>Keterangan</th><th class="num">Masuk</th><th class="num">Keluar</th></tr></thead>
                <tbody>
                @forelse ($movements as $movement)
                    @php [$gradient, $color] = $styles[$movement->warehouse->type] ?? $styles[Warehouse::RAW]; @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $movement->moved_at->translatedFormat('d M Y') }}</td>
                        <td class="whitespace-nowrap"><span class="inline-flex items-center gap-2 text-[12px] font-medium text-ink"><span class="size-2.5 rounded-full bg-linear-to-br {{ $gradient }}"></span>{{ $movement->warehouse->typeLabel() }}</span></td>
                        <td><a href="{{ route('admin.materials.show', $movement->material) }}" class="link">{{ $movement->material->label() }}</a></td>
                        <td><span class="rounded-md bg-white px-2 py-0.5 text-[11.5px] font-medium text-ink-soft shadow-[0_2px_6px_-3px_rgb(60_66_110/0.35)]">{{ $movement->typeLabel() }}</span></td>
                        <td class="max-w-72 truncate text-[12px] text-muted" title="{{ $movement->notes }}">{{ $movement->notes }}</td>
                        <td class="num">@if ((float) $movement->qty_in)<span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[12px] font-semibold text-emerald-600">+{{ qty($movement->qty_in, 2) }} {{ $movement->material->stockUnit() }}</span>@endif</td>
                        <td class="num">@if ((float) $movement->qty_out)<span class="rounded-full bg-rose-50 px-2.5 py-1 text-[12px] font-semibold text-rose-600">−{{ qty($movement->qty_out, 2) }} {{ $movement->material->stockUnit() }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-12 text-center text-muted">
                        <span class="mx-auto mb-3 grid size-12 place-items-center rounded-2xl bg-white text-[#6b7cf5] shadow-(--shadow-soft)"><svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ AdminNavigation::icon('warehouse') }}"/></svg></span>
                        Gudang masih kosong. Isi lewat <a href="{{ route('admin.purchases.create') }}" class="link">pembelian</a> atau <a href="{{ route('admin.adjustments.create') }}" class="link">stok awal</a>.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
