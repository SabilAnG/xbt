<section class="card overflow-hidden">
    <div class="card-header"><h2 class="card-title">{{ $title }}</h2></div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
            <tr>
                <th>Tanggal</th>
                @if ($showComponent)<th>Komponen</th>@endif
                <th>Jenis</th>
                <th>Keterangan</th>
                <th class="num">Masuk</th>
                <th class="num">Keluar</th>
                <th class="num">Saldo</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($movements as $movement)
                <tr>
                    <td class="whitespace-nowrap">{{ $movement->moved_at->translatedFormat('d M Y') }}</td>
                    @if ($showComponent)
                        <td class="whitespace-nowrap"><a href="{{ route('admin.components.show', $movement->component_id) }}" class="link">{{ $movement->component?->name }}</a></td>
                    @endif
                    <td class="whitespace-nowrap">{{ \App\Models\ComponentMovement::LABELS[$movement->type] ?? $movement->type }}</td>
                    <td class="min-w-48 text-xs">
                        {{ $movement->notes }}
                        @if ($movement->consumes_materials)<span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">bahan baku terpotong</span>@endif
                    </td>
                    <td class="num text-emerald-600">{{ (float) $movement->qty_in ? '+'.qty($movement->qty_in) : '' }}</td>
                    <td class="num text-rose-600">{{ (float) $movement->qty_out ? '−'.qty($movement->qty_out) : '' }}</td>
                    <td class="num font-semibold">{{ qty($movement->balance_after) }}</td>
                    <td class="text-right">
                        @if ($movement->isManual())
                            <x-admin.delete-button :action="route('admin.warehouses.movements.destroy', $movement)" confirm="Hapus mutasi ini? Bahan baku yang terpotong akan dikembalikan." />
                        @else
                            <a href="{{ route('admin.purchases.index') }}" class="text-xs text-slate-400 hover:text-slate-600" title="Hapus lewat menu Pembelian Bahan">dari pembelian</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $showComponent ? 8 : 7 }}" class="py-8 text-center text-slate-500">Belum ada mutasi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
