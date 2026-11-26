@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'href' => null, 'icon' => null, 'percent' => null, 'trend' => null, 'title' => null])
@php
    $tag = $href ? 'a' : 'div';
    // Arah panah: turun untuk angka yang "keluar/kurang", naik untuk lainnya.
    $down = $trend ? $trend === 'down' : in_array($tone, ['danger', 'warning'], true);
    $fill = $percent === null ? 72 : max(6, min(100, (float) $percent));
    $valueTone = ['danger' => 'text-rose-600', 'warning' => 'text-amber-600', 'success' => 'text-emerald-600'][$tone] ?? 'text-ink';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif data-tone="{{ $tone }}"
    {{ $attributes->class(['stat card group relative flex min-w-0 items-center gap-3.5 px-4 py-4 2xl:gap-4 2xl:px-6', 'transition hover:-translate-y-0.5' => $href]) }}>
    <span class="relative grid size-[54px] shrink-0 place-items-center 2xl:size-[58px]">
        <svg viewBox="0 0 60 60" class="absolute inset-0 size-full -rotate-[200deg]" aria-hidden="true">
            <circle cx="30" cy="30" r="25" fill="none" stroke="#e9ebf2" stroke-width="7"/>
            <circle cx="30" cy="30" r="25" fill="none" stroke-width="7" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ round($fill, 1) }} 100" class="ring-arc"/>
        </svg>
        <span class="relative grid size-[36px] place-items-center rounded-full bg-linear-to-br from-white to-[#eef0f6] text-ink shadow-[0_5px_12px_-5px_rgb(60_66_110/0.55)]">
            <svg class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="{{ $down ? 'M17 7 7 17m0 0h8m-8 0V9' : 'M7 17 17 7m0 0H9m8 0v8' }}"/>
            </svg>
        </span>
    </span>
    <span class="min-w-0 flex-1">
        <span class="block truncate text-xl leading-tight font-bold tracking-tight tabular-nums sm:text-[24px] 2xl:text-[27px] {{ $valueTone }}" title="{{ $title ?? $value }}">{{ $value }}</span>
        <span class="mt-0.5 block truncate text-[14px] font-medium text-ink-soft" title="{{ $label }}">{{ $label }}</span>
        @if ($hint)
            <span class="block truncate text-[11.5px] text-muted" title="{{ $hint }}">{{ $hint }}</span>
        @endif
    </span>
</{{ $tag }}>
