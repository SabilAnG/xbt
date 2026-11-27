@extends('layouts.app')

@section('title', $product->name)
@section('body-class', 'has-buybar')
@section('m-back', route('products.index', [], false))
@section('m-title', $product->name)

@push('head')
<style>
        .product-title {
            font-weight: 600;
            color: var(--primary-color, '#ff6600');
            position: relative;
            display: inline-block;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }

        .product-title .title-underline {
            display: block;
            width: 80px;
            height: 3px;
            background-color: var(--primary-color, '#ff6600');
            border-radius: 2px;
            margin-top: 6px;
        }

        .product-desc {
            font-size: 18px;
            line-height: 1.7;
            color: #ddd;
            margin-top: 15px;
        }

        .product-desc .highlight {
            font-weight: 600;
            color: var(--primary-color, '#ff6600');
        }

        article.product-description h2 {
            font-size: var(--h3-font-size);
            font-weight: var(--h3-font-weight);
            position: relative;
            display: inline-block;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }

        article.product-description h3 {
            font-size: var(--h4-font-size);
            font-weight: var(--h4-font-weight);
            position: relative;
            display: inline-block;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }

        article.product-description ul {}

        article.product-description ul li {}

        article.product-description ul li:hover {}

        #checkout-contact-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            justify-content: center;
        }

        @media (max-width: 767px) {

            /* In Mobile Display 1 Column */
            #checkout-contact-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
<div class="no-bottom no-top" id="content">
            <div id="top"></div>
    @include('partials.m-pdp', ['product' => $product, 'others' => $others])
                <section class="pt-120 sm-pt-0 m-detail-section m-hide-mobile">
        <div class="container">
            <a href="{{ route('products.index', [], false) }}" class="d-none d-md-inline-flex align-items-center gap-2 mb-4 product-back-link"
                onclick="return goBack(this.getAttribute('href'))">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15 18 9 12l6-6"/></svg>
                Kembali ke produk
            </a>
            <div class="row gy-4 gx-5">
                <!-- Gallery -->
                <div class="col-md-6">
                    <div id="sync1" class="owl-carousel owl-theme">
                        @foreach ($product->images as $image)
                            <div class="item">
                                @if ($image->isVideo())
                                    <video controls playsinline preload="metadata" class="w-100" style="aspect-ratio:1;object-fit:contain;background:#000">
                                        <source src="{{ asset($image->path) }}#t=0.1" type="{{ $image->videoMime() }}">
                                    </video>
                                @else
                                    <img src="{{ asset($image->path) }}" class="w-100" alt="N/A">
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div id="sync2" class="owl-carousel owl-theme mt-3">
                        @foreach ($product->images as $image)
                            <div class="item" style="position:relative">
                                @if ($image->isVideo())
                                    <video src="{{ asset($image->path) }}#t=0.5" muted playsinline preload="metadata" class="w-100" style="aspect-ratio:1;object-fit:cover;pointer-events:none"></video>
                                    <span style="position:absolute;inset:0;display:grid;place-items:center;background:rgba(0,0,0,.35);color:#fff;pointer-events:none"><svg width="22" height="22" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
                                @else
                                    <img src="{{ asset($image->thumb()) }}" class="w-100" alt="{{ $loop->first ? 'N/A' : '' }}">
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Info Produk -->
                <div class="col-md-6 m-detail-info">
                    <h2 class="fs-40">{{ $product->name }}</h2>
                    <p class="col-lg-10">{{ $product->fitment }}</p>

                    <div class="d-flex mb-4 align-items-center">
                                                <div>
                            <h3 class="fs-32 mb-0 me-2">
                                {{ $product->formattedPrice() }}
                            </h3>
                            @if ($product->item)
                                <p class="mb-0 mt-1" style="opacity:.75">
                                    {{ $product->availableStock() > 0 ? 'Stok tersedia: '.qty($product->availableStock()).' unit' : 'Stok habis — bisa pre-order' }}
                                </p>
                            @endif
                        </div>
                                            </div>

                    <button type="button" class="btn-add-cart w-100 mt-4" data-cart-add='@json($product->cartPayload())'>
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6.2M9 20.5a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Zm8 0a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Z"/></svg>
                        Tambah ke keranjang
                    </button>

                    <!-- Buy Action -->
                    <a class="btn-main mt-3 w-100" id="btn-buy-now" href="javascript:void(0)">
                        <span>Buy Now</span>
                    </a>

                    <div id="checkout-contact-list" class="mt-2" style="display: none">
                        @foreach (config('site.checkout_handlers', []) as $handler)
                            <a data-product-direct-checkout-url="{{ route('redirect.product-checkout', ['product' => $product->slug, 'customer_support' => $handler['desk']]) }}"
                                data-handler="{{ $handler['label'] }}" class="btn-main product-direct-checkout w-100"
                                href="javascript:void(0)">
                                <span>{{ $handler['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="row mt-5 m-detail-desc">
                <div class="col-md-12">
                    <h3 class="product-title">
                        Description
                        <span class="title-underline"></span>
                    </h3>

                    <article class="product-description">
                        {!! safe_html($product->description) !!}
                    </article>
                </div>
            </div>

        </div>
    </section>
        </div>
@endsection

@push('mobile-bar')
    <div class="m-buybar">
        <a class="m-buybar-chat" href="{{ route('redirect.product-checkout', ['product' => $product->slug, 'customer_support' => 2]) }}" target="_blank" rel="noopener" aria-label="Chat admin 2">
            <i class="fa-regular fa-comment-dots" style="font-size:18px"></i>
        </a>
        <button type="button" class="m-buybar-cart" data-cart-add='@json($product->cartPayload())'>
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6.2M9 20.5a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Zm8 0a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Z"/></svg>
            + Keranjang
        </button>
        <a class="m-buybar-buy" href="{{ route('redirect.product-checkout', ['product' => $product->slug, 'customer_support' => 1]) }}" target="_blank" rel="noopener">
            <i class="fa-brands fa-whatsapp" style="font-size:18px"></i> Beli
        </a>
    </div>
@endpush

@push('scripts')
<script>
        $(() => {
            const $contactList = $('#checkout-contact-list');
            const $buyBtn = $('#btn-buy-now');

            $buyBtn.on('click', function() {
                $contactList.stop().slideToggle(300, function() {
                    $(this).toggleClass('hidden', !$(this).is(':visible'));
                });
            });

            $("a.product-direct-checkout").click(function(e) {
                e.preventDefault();

                const checkoutUrl = $(this).data("product-direct-checkout-url");
                if (checkoutUrl) {
                    window.open(checkoutUrl, "_blank");
                }
            });
        });
    </script>
@endpush
