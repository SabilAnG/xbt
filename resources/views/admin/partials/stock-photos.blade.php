{{-- Foto stok dari stok opname. Butuh: $photos (koleksi StockPhoto). --}}
@if ($photos->isNotEmpty())
    <section class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Foto stok</h2>
            <a href="{{ route('admin.stock-opname.index') }}" class="link text-xs">Stok opname</a>
        </div>
        <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-4 xl:grid-cols-6">
            @foreach ($photos as $photo)
                <a href="{{ $photo->url() }}" target="_blank" class="group block overflow-hidden rounded-[12px] bg-surface ring-1 ring-line">
                    <img src="{{ $photo->url() }}" alt="Foto stok {{ $photo->taken_at->translatedFormat('d M Y') }}" loading="lazy" class="aspect-square w-full object-cover transition group-hover:scale-105">
                    <span class="block px-2.5 py-1.5 text-[11px] leading-tight text-muted">
                        {{ $photo->taken_at->translatedFormat('d M Y') }}{{ $photo->counted_qty !== null ? ' · '.qty($photo->counted_qty) : '' }}
                        @if ($photo->counted_by || $photo->warehouse)<span class="block truncate">{{ collect([$photo->counted_by, $photo->warehouse?->name])->filter()->implode(' · ') }}</span>@endif
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
