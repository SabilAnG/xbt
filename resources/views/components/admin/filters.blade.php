{{-- Bar filter GET; isi kontrol lewat slot. --}}
<form method="GET" class="card flex flex-col gap-3 p-3.5 sm:flex-row sm:flex-wrap sm:items-end sm:p-4 [&_.label]:text-xs [&_.label]:text-slate-500">
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
