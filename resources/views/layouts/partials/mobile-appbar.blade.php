{{-- App bar HP: kembali · judul · notifikasi · akun. Hanya di bawah lg. --}}
<header class="m-appbar lg:hidden">
    @if ($isDashboard)
        <a href="{{ route('admin.dashboard') }}" class="m-appbar-logo" aria-label="Ringkasan">
            <img src="{{ asset('assets/images/logo-mark.png') }}" alt="">
        </a>
    @else
        <a href="{{ $backUrl }}" class="m-icon-btn" aria-label="Kembali" data-back>
            <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15 18 9 12l6-6"/></svg>
        </a>
    @endif

    <div class="min-w-0 flex-1">
        @if ($isDashboard)
            <p class="m-appbar-kicker">{{ now()->translatedFormat('l, d M') }}</p>
            <h1 class="m-appbar-title">Halo, {{ $firstName }}</h1>
        @else
            @if ($currentGroup && $currentGroup !== 'Ringkasan')
                <p class="m-appbar-kicker">{{ $currentGroup }}</p>
            @endif
            <h1 class="m-appbar-title">{{ $title ?? 'Admin' }}</h1>
        @endif
    </div>

    <button type="button" class="m-icon-btn relative" data-sheet-open="notifications" aria-label="Notifikasi">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5a6.5 6.5 0 0 0-6.5 6.5v4.3L4 16v1.5h16V16l-1.5-2.7V9A6.5 6.5 0 0 0 12 2.5ZM9.5 19a2.5 2.5 0 0 0 5 0h-5Z"/></svg>
        @if ($notifCount > 0)<span class="m-badge">{{ $notifCount }}</span>@endif
    </button>
    <button type="button" class="m-avatar" data-sheet-open="menu" aria-label="Akun & menu">
        {{ strtoupper($initials) }}
        @unless ($user?->hasTwoFactor())<span class="m-avatar-dot"></span>@endunless
    </button>
</header>
