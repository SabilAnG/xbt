@extends('layouts.app')

@section('title', "Single Product")

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
                <section class="pt-120 sm-pt-0">
        <div class="container">
            <div class="row gy-4 gx-5">
                <!-- Gallery -->
                <div class="col-md-6">
                    <div id="sync1" class="owl-carousel owl-theme">
                        @foreach ($product->images as $image)
                            <div class="item">
                                <img src="{{ asset($image->path) }}" class="w-100" alt="N/A">
                            </div>
                        @endforeach
                    </div>

                    <div id="sync2" class="owl-carousel owl-theme mt-3">
                        @foreach ($product->images as $image)
                            <div class="item">
                                <img src="{{ asset($image->thumb()) }}" class="w-100" alt="{{ $loop->first ? 'N/A' : '' }}">
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Info Produk -->
                <div class="col-md-6">
                    <h2 class="fs-40">{{ $product->name }}</h2>
                    <p class="col-lg-10">{{ $product->fitment }}</p>

                    <div class="d-flex mb-4 align-items-center">
                                                <div>
                            <h3 class="fs-32 mb-0 me-2">
                                ${{ number_format($product->price, 2) }}
                            </h3>
                        </div>
                                            </div>

                    <!-- Buy Action -->
                    <a class="btn-main mt-4 w-100" id="btn-buy-now" href="javascript:void(0)">
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
            <div class="row mt-5">
                <div class="col-md-12">
                    <h3 class="product-title">
                        Description
                        <span class="title-underline"></span>
                    </h3>

                    <article class="product-description">
                        {!! $product->description !!}
                    </article>
                </div>
            </div>

        </div>
    </section>
        </div>
@endsection

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
