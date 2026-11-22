@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'href' => null])
@php
    $tones = [
        'default' => 'text-zinc-900',
        'danger' => 'text-red-600',
        'warning' => 'text-amber-600',
        'success' => 'text-emerald-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['card block p-4', 'transition hover:border-brand-500/50 hover:shadow' => $href]) }}>
    <p class="text-xs font-medium text-zinc-500">{{ $label }}</p>
    <p class="mt-1.5 text-xl font-semibold tabular-nums {{ $tones[$tone] ?? $tones['default'] }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-zinc-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>
