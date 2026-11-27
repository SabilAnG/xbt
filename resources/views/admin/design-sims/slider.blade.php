{{-- Ukuran: slider + angka yang saling mengikuti. --}}
<div class="ds-slider" data-slider>
    <div class="flex items-baseline justify-between gap-3">
        <label class="label mb-0!" for="ds-{{ str_replace('.', '-', $key) }}">{{ $label }}</label>
        <span class="flex items-baseline gap-1">
            <input type="number" class="ds-number" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $value }}" data-param="{{ $key }}" aria-label="{{ $label }}">
            <span class="text-[12px] text-muted">{{ $unit }}</span>
        </span>
    </div>
    <input type="range" id="ds-{{ str_replace('.', '-', $key) }}" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $value }}" data-range="{{ $key }}">
</div>
