{{-- Siluet samping knalpot per gaya silincer. --}}
<svg viewBox="0 0 64 28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M3 6c0 8 4 12 12 13h4"/>
    @switch($style)
        @case('megaphone')
            <path d="M19 16l38-8v16l-38-4z"/><path d="M57 8v16"/>
            @break
        @case('oval')
            <rect x="19" y="11" width="34" height="13" rx="6.5"/><path d="M53 17.5h7"/>
            @break
        @case('hexagon')
            <path d="M22 10h28l4 7.5-4 7.5H22l-4-7.5z"/><path d="M54 17.5h6"/>
            @break
        @case('round')
            <rect x="19" y="10" width="34" height="15" rx="2"/><path d="M53 17.5h7"/>
            @break
        @default
            <path d="M19 12l34-4v18l-34-4z"/><path d="M53 13l7-2"/>
    @endswitch
</svg>
