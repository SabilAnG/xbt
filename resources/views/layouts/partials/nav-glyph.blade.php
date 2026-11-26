{{-- Ikon solid duotone per grup menu (biru → ungu), gaya rail sidebar. --}}
@php
    $a = 'url(#nav-a)';
    $b = 'url(#nav-b)';
@endphp
<svg viewBox="0 0 24 24" aria-hidden="true">
    @switch($group)
        @case('Ringkasan')
            <rect x="2" y="2" width="20" height="20" rx="6" fill="{{ $a }}"/>
            <path d="M6.5 15.5 10 11.5l3 2.5 4.5-5.5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            @break
        @case('Pembelian')
            <path d="M8.5 8V6.8a3.5 3.5 0 0 1 7 0V8" fill="none" stroke="{{ $b }}" stroke-width="2.2" stroke-linecap="round"/>
            <path d="M4.6 8.2A1.5 1.5 0 0 1 6.1 7h11.8a1.5 1.5 0 0 1 1.5 1.2l1.4 11.2a2 2 0 0 1-2 2.3H5.2a2 2 0 0 1-2-2.3L4.6 8.2Z" fill="{{ $a }}"/>
            <path d="M9 12.5a3 3 0 0 0 6 0" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
            @break
        @case('Penjualan')
            <path d="M2.5 3.5h2.6l1.1 3.2" fill="none" stroke="{{ $b }}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M5.6 6.5h15a1 1 0 0 1 1 1.3l-2 7a1.6 1.6 0 0 1-1.6 1.2H8.6A1.6 1.6 0 0 1 7 14.8L5.6 6.5Z" fill="{{ $a }}"/>
            <circle cx="9" cy="19.6" r="1.9" fill="{{ $b }}"/>
            <circle cx="17" cy="19.6" r="1.9" fill="{{ $b }}"/>
            @break
        @case('Katalog')
            <rect x="2.5" y="2.5" width="8.5" height="8.5" rx="2.6" fill="{{ $a }}"/>
            <rect x="13" y="2.5" width="8.5" height="8.5" rx="4.25" fill="{{ $b }}"/>
            <rect x="2.5" y="13" width="8.5" height="8.5" rx="2.6" fill="{{ $b }}"/>
            <rect x="13" y="13" width="8.5" height="8.5" rx="2.6" fill="{{ $a }}"/>
            @break
        @case('Keuangan')
            <rect x="2" y="5" width="14" height="17" rx="3" fill="{{ $b }}"/>
            <rect x="6" y="2" width="16" height="18" rx="3.2" fill="{{ $a }}"/>
            <path d="M16.6 8.4c-.4-.8-1.3-1.3-2.6-1.3-1.5 0-2.5.7-2.5 1.8 0 2.6 5.2 1.3 5.2 4 0 1.1-1.1 1.9-2.7 1.9-1.3 0-2.3-.5-2.7-1.4M14 5.8v1.3m0 7.9v1.3" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round"/>
            @break
        @case('Gudang')
            <path d="M2.5 9.3 12 3l9.5 6.3V20a1.5 1.5 0 0 1-1.5 1.5H4A1.5 1.5 0 0 1 2.5 20V9.3Z" fill="{{ $a }}"/>
            <rect x="7" y="12.5" width="10" height="9" rx="1.2" fill="{{ $b }}"/>
            <path d="M7 15.5h10M7 18.5h10" stroke="#fff" stroke-width="1.4"/>
            @break
        @case('Produksi')
            <g fill="{{ $a }}">
                @foreach ([0, 45, 90, 135, 180, 225, 270, 315] as $angle)
                    <rect x="10.2" y="1.6" width="3.6" height="5" rx="1.2" transform="rotate({{ $angle }} 12 12)"/>
                @endforeach
                <circle cx="12" cy="12" r="7.4"/>
            </g>
            <circle cx="12" cy="12" r="3.4" fill="#fff"/>
            <circle cx="12" cy="12" r="1.9" fill="{{ $b }}"/>
            @break
        @case('Pekerja')
            <circle cx="16.5" cy="7.5" r="3.2" fill="{{ $b }}"/>
            <path d="M11.5 20.5c0-3.3 2.2-6 5-6s5 2.7 5 6v.5h-10v-.5Z" fill="{{ $b }}"/>
            <circle cx="9" cy="7" r="3.8" fill="{{ $a }}"/>
            <path d="M2 20.5c0-4 3.1-7.2 7-7.2s7 3.2 7 7.2v.5H2v-.5Z" fill="{{ $a }}"/>
            @break
        @default
            <path d="M11 3.1A9 9 0 1 0 20.9 13H11V3.1Z" fill="{{ $a }}"/>
            <path d="M13 1.8a9.2 9.2 0 0 1 9.2 9.2H13V1.8Z" fill="{{ $b }}"/>
    @endswitch
</svg>
