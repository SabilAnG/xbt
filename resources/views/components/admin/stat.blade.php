@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'href' => null, 'icon' => null])
@php
    $tones = [
        'default' => ['value' => 'text-slate-900', 'icon' => 'bg-slate-100 text-slate-500'],
        'danger' => ['value' => 'text-rose-600', 'icon' => 'bg-rose-50 text-rose-500'],
        'warning' => ['value' => 'text-amber-600', 'icon' => 'bg-amber-50 text-amber-500'],
        'success' => ['value' => 'text-emerald-600', 'icon' => 'bg-emerald-50 text-emerald-500'],
        'brand' => ['value' => 'text-slate-900', 'icon' => 'bg-brand-50 text-brand-600'],
    ];
    $style = $tones[$tone] ?? $tones['default'];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['card group relative flex min-w-0 flex-col p-4 sm:p-5', 'transition hover:-translate-y-px hover:border-slate-300 hover:shadow-md' => $href]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-[12.5px] leading-snug font-medium text-slate-500">{{ $label }}</p>
        @if ($icon)
            <span class="grid size-8 shrink-0 place-items-center rounded-lg {{ $style['icon'] }}">
                <svg class="size-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>
            </span>
        @endif
    </div>
    <p class="mt-2 truncate text-lg font-semibold tracking-tight tabular-nums sm:text-[22px] {{ $style['value'] }}" title="{{ $value }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 truncate text-xs text-slate-500" title="{{ $hint }}">{{ $hint }}</p>
    @endif
    @if ($href)
        <svg class="absolute right-4 bottom-4 size-4 text-slate-300 opacity-0 transition group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M7 17 17 7m0 0H8m9 0v9"/></svg>
    @endif
</{{ $tag }}>
