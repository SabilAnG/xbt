@props(['value'])
@php
    $styles = [
        'HABIS' => ['bg-rose-50 text-rose-700 ring-rose-600/15', 'bg-rose-500'],
        'MENIPIS' => ['bg-amber-50 text-amber-800 ring-amber-600/20', 'bg-amber-500'],
        'AMAN' => ['bg-emerald-50 text-emerald-700 ring-emerald-600/15', 'bg-emerald-500'],
        'LUNAS' => ['bg-emerald-50 text-emerald-700 ring-emerald-600/15', 'bg-emerald-500'],
        'BELUM LUNAS' => ['bg-rose-50 text-rose-700 ring-rose-600/15', 'bg-rose-500'],
        'PERLU DIRINCI' => ['bg-rose-50 text-rose-700 ring-rose-600/15', 'bg-rose-500'],
        'ESTIMASI' => ['bg-amber-50 text-amber-800 ring-amber-600/20', 'bg-amber-500'],
        'OK' => ['bg-slate-50 text-slate-600 ring-slate-500/15', 'bg-slate-400'],
        'AKTIF' => ['bg-emerald-50 text-emerald-700 ring-emerald-600/15', 'bg-emerald-500'],
        'NONAKTIF' => ['bg-slate-50 text-slate-500 ring-slate-500/15', 'bg-slate-300'],
        'Sudah' => ['bg-emerald-50 text-emerald-700 ring-emerald-600/15', 'bg-emerald-500'],
        'Belum' => ['bg-rose-50 text-rose-700 ring-rose-600/15', 'bg-rose-500'],
    ];
    [$badge, $dot] = $styles[$value] ?? ['bg-slate-50 text-slate-600 ring-slate-500/15', 'bg-slate-400'];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap ring-1 ring-inset', $badge]) }}>
    <span class="size-1.5 rounded-full {{ $dot }}"></span>{{ $value }}
</span>
