{{-- Bar filter GET; isi kontrol lewat slot. Di HP terlipat jadi satu tombol "Filter". --}}
@php $active = count(array_filter(request()->except('page'), fn ($value) => filled($value))); @endphp
<form method="GET" class="m-filters card flex flex-col gap-3 p-3.5 max-lg:gap-4 max-lg:p-4 sm:flex-row sm:flex-wrap sm:items-end sm:p-4 [&_.label]:text-xs [&_.label]:text-slate-500" data-filters>
    <button type="button" class="m-filters-toggle -m-1 flex min-h-11 w-[calc(100%+0.5rem)] items-center gap-3 rounded-[14px] px-2 text-left text-[15px] font-semibold text-ink" aria-expanded="false" data-filters-toggle>
        <span class="grid size-9 place-items-center rounded-[11px] bg-brand-50 text-brand-700">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16l-6 7.5V19l-4 1.5v-8L4 5Z"/></svg>
        </span>
        <span class="flex-1">Filter &amp; cari</span>
        @if ($active)<span class="rounded-full bg-brand-600 px-2.5 py-0.5 text-[12px] font-semibold text-white">{{ $active }} aktif</span>@endif
        <svg class="size-5 text-muted transition [[aria-expanded=true]>&]:rotate-180" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    {{ $slot }}
    <div class="flex gap-2 sm:ml-auto">
        @if (count(request()->except('page')))
            <a href="{{ url()->current() }}" class="btn btn-ghost">Reset</a>
        @endif
        <button class="btn btn-primary flex-1 sm:flex-none">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16l-6 7.5V19l-4 1.5v-8L4 5Z"/></svg>
            Terapkan
        </button>
    </div>
</form>
@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-filters-toggle]').forEach(button => button.addEventListener('click', () => {
                const open = button.closest('[data-filters]').classList.toggle('is-open');
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            }));
        </script>
    @endpush
@endonce
