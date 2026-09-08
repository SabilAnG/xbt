@extends('layouts.app')

@section('title', "Our Products")

@section('content')
<div class="no-bottom no-top" id="content">
            <div id="top"></div>
                <section class="bg-dark text-light relative jarallax">
    <div class="de-gradient-edge-top"></div>
    <img src="{{ content_image('products.image.1', 'assets/images/2345.png') }}" class="jarallax-img" alt="">
    <div class="container relative z-2">
        <div class="row gy-4 gx-5 justify-content-center">
            <div class="col-lg-12 text-center">
                <div class="spacer-double sm-hide"></div>
                <h1 class="mb-3 wow fadeInUp" data-wow-delay=".2s">
                    {{ content('products.text.1', 'Our Products') }}
                </h1>
                <div class="border-bottom mb-3"></div>
                <ul class="crumb wow fadeInUp">
                    <li><a href="/">Home</a></li>
                    <li class="active">{{ content('products.text.2', 'Our Products') }}</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="sw-overlay"></div>
</section>

    <section>
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-3">
                    <div class="item_filter_group">
                        <h4>{{ content('products.text.3', 'Categories') }}</h4>
                        <div class="de_form">
                            <div class="de_checkbox">
                                <input class="cat-filter" id="cat_all" name="cat_all" type="checkbox" value="all" checked>
                                <label for="cat_all">{{ content('products.text.4', 'All Categories') }}</label>
                            </div>
                                                            <div class="de_checkbox">
                                    <input class="cat-filter" id="cat_2" name="cat_2"
                                        type="checkbox" value="2">
                                    <label for="cat_2">{{ content('products.text.5', 'PIPE ONLY') }}</label>
                                </div>
                                                            <div class="de_checkbox">
                                    <input class="cat-filter" id="cat_3" name="cat_3"
                                        type="checkbox" value="3">
                                    <label for="cat_3">{{ content('products.text.6', 'FULL SYSTEM') }}</label>
                                </div>
                                                            <div class="de_checkbox">
                                    <input class="cat-filter" id="cat_4" name="cat_4"
                                        type="checkbox" value="4">
                                    <label for="cat_4">{{ content('products.text.7', 'SLIP-ON') }}</label>
                                </div>
                                                            <div class="de_checkbox">
                                    <input class="cat-filter" id="cat_5" name="cat_5"
                                        type="checkbox" value="5">
                                    <label for="cat_5">{{ content('products.text.8', 'HEADER ONLY') }}</label>
                                </div>
                                                    </div>
                    </div>

                    <div class="item_filter_group">
                        <h4>{{ content('products.text.9', 'Brands') }}</h4>
                        <div class="de_form">
                            <div class="de_checkbox">
                                <input class="brand-filter" id="brand_all" name="brand_all" type="checkbox" value="all"
                                    checked>
                                <label for="brand_all">{{ content('products.text.10', 'All Brands') }}</label>
                            </div>

                                                            <div class="de_checkbox">
                                    <input class="brand-filter" id="brand_1"
                                        name="brand_1" type="checkbox" value="1">
                                    <label for="brand_1">{{ content('products.text.11', 'Harley Davidson') }}</label>
                                </div>
                                                            <div class="de_checkbox">
                                    <input class="brand-filter" id="brand_2"
                                        name="brand_2" type="checkbox" value="2">
                                    <label for="brand_2">{{ content('products.text.12', 'kawasaki') }}</label>
                                </div>
                                                            <div class="de_checkbox">
                                    <input class="brand-filter" id="brand_3"
                                        name="brand_3" type="checkbox" value="3">
                                    <label for="brand_3">{{ content('products.text.13', 'Yamaha') }}</label>
                                </div>
                                                            <div class="de_checkbox">
                                    <input class="brand-filter" id="brand_4"
                                        name="brand_4" type="checkbox" value="4">
                                    <label for="brand_4">{{ content('products.text.14', 'BMW') }}</label>
                                </div>
                                                    </div>
                    </div>
                </div>

                <div class="col-lg-9">
                    <div class="row g-4">
                                                    @foreach ($products as $product)
                                                    <!-- product item begin -->
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <div class="de__pcard text-center">
                                    <div class="atr__images">
                                        <a href="{{ route('products.show', $product) }}">
                                            <img class="atr__image-main"
                                                src="{{ $product->cardImage() ? asset($product->cardImage()->thumb()) : asset('assets/images/workshop/fallback-1000x1000.webp') }}"
                                                onerror="this.src = '{{ content_image('products.image.2', 'assets/images/workshop/fallback-1000x1000.webp') }}'">
                                            <img class="atr__image-hover full"
                                                src="{{ $product->cardImage() ? asset($product->cardImage()->thumb()) : asset('assets/images/workshop/fallback-1000x1000.webp') }}"
                                                onerror="this.src = '{{ content_image('products.image.3', 'assets/images/workshop/fallback-1000x1000.webp') }}'">
                                        </a>
                                        <div class="atr__extra-menu">
                                            <a class="atr__quick-view" href="{{ route('products.show', $product) }}">
                                                <i class="icon_cart_alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <h3>
                                        <a href="{{ route('products.show', $product) }}" class="text-white">
                                            {{ $product->name }}
                                        </a>
                                    </h3>
                                    <div class="atr__main-price">
                                        ${{ number_format($product->price, 2) }}
                                    </div>
                                                                    </div>
                            </div>
                            <!-- product item end -->
                                                    @endforeach
                        
                                            </div>
                </div>
            </div>

        </div>
    </section>
        </div>
@endsection

@push('scripts')
<script>
        $(() => {
            function syncFromUrl() {
                let params = new URLSearchParams(window.location.search);

                let brands = params.get("brands") ? params.get("brands").split(",") : [];
                let cats = params.get("cats") ? params.get("cats").split(",") : [];

                $(".brand-filter, #brand_all").prop("checked", false);
                $(".cat-filter, #cat_all").prop("checked", false);

                // Set Brand checkbox
                if (brands.length > 0) {
                    if (brands.includes("all")) {
                        $("#brand_all").prop("checked", true);
                    } else {
                        brands.forEach(val => {
                            $(`#brand_${val}`).prop("checked", true);
                        });
                        $("#brand_all").prop("checked", false);
                    }
                } else {
                    $("#brand_all").prop("checked", true);
                }

                // Set Category checkbox
                if (cats.length > 0) {
                    if (cats.includes("all")) {
                        $("#cat_all").prop("checked", true);
                    } else {
                        cats.forEach(val => {
                            $(`#cat_${val}`).prop("checked", true);
                        });
                        $("#cat_all").prop("checked", false);
                    }
                } else {
                    $("#cat_all").prop("checked", true);
                }
            }

            syncFromUrl();

            $(".cat-filter, .brand-filter, #cat_all, #brand_all").change(function() {
                let brands = [];
                let categories = [];

                // Handle Categories
                if (this.id === "cat_all") {
                    if (this.checked) {
                        $(".cat-filter").prop("checked", false);
                        $("#cat_all").prop("checked", true);
                    }
                } else if ($(this).hasClass("cat-filter") && this.checked) {
                    $("#cat_all").prop("checked", false);
                }

                // Handle Brands
                if (this.id === "brand_all") {
                    if (this.checked) {
                        $(".brand-filter").prop("checked", false);
                        $("#brand_all").prop("checked", true);
                    }
                } else if ($(this).hasClass("brand-filter") && this.checked) {
                    $("#brand_all").prop("checked", false);
                }

                $(".brand-filter:checked").toArray().forEach(el => {
                    brands.push(el.value);
                });

                $(".cat-filter:checked").toArray().forEach(el => {
                    categories.push(el.value);
                });

                let params = new URLSearchParams(window.location.search);
                if (brands.length > 0) {
                    params.set("brands", brands.join(","));
                } else {
                    params.delete("brands");
                }

                if (categories.length > 0) {
                    params.set("cats", categories.join(","));
                } else {
                    params.delete("cats");
                }

                let newUrl = window.location.pathname + "?" + params.toString();

                window.__redirect(newUrl);
            });
        });
    </script>
@endpush
