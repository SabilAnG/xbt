@props(['name', 'label', 'hint' => null, 'type' => 'text', 'value' => null, 'required' => false])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }}@if ($required)<span class="text-red-500"> *</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" class="input" {{ $attributes->except('class')->merge(['rows' => 3]) }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" class="input" @required($required) {{ $attributes->except('class') }}>
    @endif
    @if ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="error">{{ $message }}</p>
    @enderror
</div>
