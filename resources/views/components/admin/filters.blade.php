{{-- Bar filter GET; isi kontrol lewat slot. --}}
<form method="GET" class="card flex flex-wrap items-end gap-3 p-4">
    {{ $slot }}
    <div class="flex gap-2">
        <button class="btn btn-primary">Terapkan</button>
        @if (count(request()->except('page')))
            <a href="{{ url()->current() }}" class="btn">Reset</a>
        @endif
    </div>
</form>
