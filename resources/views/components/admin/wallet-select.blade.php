@props(['name' => 'wallet_id', 'label' => 'Dompet', 'value' => null, 'hint' => null, 'placeholder' => '— tidak dicatat di dompet —'])
@php
    $wallets = \App\Models\Wallet::query()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', old($name, $value)))
        ->orderBy('sort_order')->orderBy('name')->get();
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}" class="input">
        <option value="">{{ $placeholder }}</option>
        @foreach ($wallets as $wallet)
            <option value="{{ $wallet->id }}" @selected((string) old($name, $value) === (string) $wallet->id)>
                {{ $wallet->name }}{{ $wallet->account_number ? ' · '.$wallet->account_number : '' }} — saldo {{ rupiah($wallet->current_balance) }}
            </option>
        @endforeach
    </select>
    @if ($wallets->isEmpty())
        <p class="hint">Belum ada dompet. <a href="{{ route('admin.wallets.create') }}" class="link">Tambah dompet</a> dulu.</p>
    @elseif ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
    @error($name)<p class="error">{{ $message }}</p>@enderror
</div>
