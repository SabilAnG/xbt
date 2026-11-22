@props(['value'])
@php
    $styles = [
        'HABIS' => 'bg-red-50 text-red-700 ring-red-200',
        'MENIPIS' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'AMAN' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'LUNAS' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'BELUM LUNAS' => 'bg-red-50 text-red-700 ring-red-200',
        'PERLU DIRINCI' => 'bg-red-50 text-red-700 ring-red-200',
        'ESTIMASI' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'AKTIF' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'NONAKTIF' => 'bg-zinc-100 text-zinc-500 ring-zinc-200',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap ring-1 ring-inset', $styles[$value] ?? 'bg-zinc-100 text-zinc-600 ring-zinc-200']) }}>{{ $value }}</span>
