{{-- Kartu produk gaya aplikasi e-commerce (dipakai beranda versi HP). --}}
@php $image = $product->cardImage(); @endphp
<a href="{{ route('products.show', $product->slug) }}" class="m-card">
    <span class="m-card-img">
        <img src="{{ $image ? asset($image->thumb()) : asset('assets/images/workshop/fallback-1000x1000.webp') }}" alt="{{ $product->name }}" loading="lazy">
        @if ($product->item)
            <span @class(['m-card-badge', 'is-out' => $product->availableStock() <= 0])>{{ $product->availableStock() > 0 ? 'Ready' : 'Pre-order' }}</span>
        @endif
    </span>
    <span class="m-card-body">
        <span class="m-card-name">{{ $product->name }}</span>
        @if ($product->fitment)
            <span class="m-card-fit">{{ $product->fitment }}</span>
        @endif
        <span class="m-card-price">{{ $product->formattedPrice() }}</span>
    </span>
</a>
