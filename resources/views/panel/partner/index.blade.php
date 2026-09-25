<x-panel.layout judul="Partners" keterangan="Toko yang menumpang sistem ini, masing-masing dengan databasenya sendiri.">

    <form method="GET" class="kartu mb-4 flex flex-wrap items-center gap-3 p-3">
        <label class="relative min-w-0 flex-1 basis-56">
            <span class="sr-only">Cari partner</span>
            <x-panel.ikon nama="cari" kelas="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="cari" value="{{ $cari }}" data-cari placeholder="Cari nama, id, atau telepon…" class="isian pl-10">
        </label>

        <select name="status" onchange="this.form.submit()" class="isian w-auto min-w-40">
            <option value="">Semua status</option>
            @foreach (App\Models\Tenant::STATUSES as $nilai => $label)
                <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
            @endforeach
        </select>

        @if ($menunggu > 0)
            <a href="{{ route('panel.partner.index', ['status' => App\Models\Tenant::MENUNGGU]) }}"
               class="shrink-0 rounded-full bg-kuning-400/25 px-3 py-1 text-[0.8125rem] font-semibold text-amber-700">
                {{ $menunggu }} menunggu persetujuan
            </a>
        @endif

        <p class="ml-auto shrink-0 px-1 text-[0.8125rem] text-slate-500">
            {{ number_format($partner->total(), 0, ',', '.') }} partner
        </p>
    </form>

    <div class="kartu overflow-hidden">
        <div class="max-h-[calc(100dvh-16rem)] overflow-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        <th class="th">Toko</th>
                        <th class="th">Pemilik</th>
                        <th class="th">Masa pakai</th>
                        <th class="th">Status</th>
                        <th class="th w-px text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($partner as $p)
                        <tr class="transition hover:bg-ungu-500/5">
                            <td class="td">
                                <p class="font-medium text-slate-800">{{ $p->name }}</p>
                                <p class="text-[0.75rem] text-slate-400">{{ $p->alamatRingkas() }}</p>
                            </td>
                            <td class="td">
                                <p class="text-slate-700">{{ $p->owner_name ?: '—' }}</p>
                                <p class="text-[0.75rem] text-slate-400">{{ $p->owner_phone }}</p>
                            </td>
                            <td class="td text-slate-600">{{ $p->sisaMasa() }}</td>
                            <td class="td">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[0.75rem] font-medium',
                                    'bg-mint-500/15 text-emerald-700' => $p->isAktif(),
                                    'bg-kuning-400/25 text-amber-700' => $p->status === App\Models\Tenant::MENUNGGU,
                                    'bg-merah-500/10 text-merah-500' => $p->status === App\Models\Tenant::DITOLAK,
                                    'bg-slate-100 text-slate-500' => $p->status === App\Models\Tenant::AKTIF && $p->kedaluwarsa(),
                                ])>{{ $p->displayStatus() }}</span>
                            </td>
                            <td class="td text-right">
                                <a href="{{ route('panel.partner.show', $p) }}"
                                   class="rounded-lg px-2.5 py-1 text-[0.8125rem] font-medium text-ungu-600 transition hover:bg-ungu-500/10">Kelola</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center">
                                <p class="text-sm font-medium text-slate-600">
                                    {{ $cari !== '' || $status ? 'Tidak ada yang cocok' : 'Belum ada partner' }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($partner->hasPages())
            <div class="border-t border-slate-100 px-4 py-2.5 text-[0.8125rem]">{{ $partner->links('panel.partials.paginasi') }}</div>
        @endif
    </div>
</x-panel.layout>
