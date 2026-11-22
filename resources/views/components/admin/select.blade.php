@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => '— pilih —', 'hint' => null, 'required' => false])
{{-- $options: [value => label] --}}
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }}@if ($required)<span class="text-red-500"> *</span>@endif</label>
    <select id="{{ $name }}" name="{{ $name }}" class="input" @required($required) {{ $attributes->except('class') }}>
        @if ($placeholder !== false)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="error">{{ $message }}</p>
    @enderror
</div>
