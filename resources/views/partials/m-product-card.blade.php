{{-- Kartu produk gaya aplikasi e-commerce (beranda, daftar produk, rekomendasi di HP). --}}
@php $image = $product->cardImage(); @endphp
<div class="m-card" data-name="{{ strtolower($product->name.' '.$product->fitment) }}" data-price="{{ (float) $product->price }}" data-order="{{ $product->sort_order }}" data-id="{{ $product->id }}">
    <a href="{{ route('products.show', $product->slug) }}" class="m-card-link">
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
    <div class="m-card-actions">
        <button type="button" class="m-card-cart" data-cart-add='@json($product->cartPayload())' aria-label="Tambah {{ $product->name }} ke keranjang" title="Tambah ke keranjang">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6.2M9 20.5a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Zm8 0a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1ZM13 9v4m-2-2h4"/></svg>
        </button>
        <a class="m-card-buy" href="{{ route('redirect.product-checkout', ['product' => $product->slug, 'customer_support' => 1]) }}" target="_blank" rel="noopener">Beli</a>
    </div>
</div>
