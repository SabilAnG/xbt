@extends('layouts.app')

@section('title', "About")

@section('content')
<div class="no-bottom no-top" id="content">
            <div id="top"></div>
                <section class="bg-dark text-light relative jarallax">
    <div class="de-gradient-edge-top"></div>
    <img src="{{ content_image('about.image.1', 'assets/images/2345.png') }}" class="jarallax-img" alt="">
    <div class="container relative z-2">
        <div class="row gy-4 gx-5 justify-content-center">
            <div class="col-lg-12 text-center">
                <div class="spacer-double sm-hide"></div>
                <h1 class="mb-3 wow fadeInUp" data-wow-delay=".2s">
                    {{ content('about.text.1', 'About Us') }}
                </h1>
                <div class="border-bottom mb-3"></div>
                <ul class="crumb wow fadeInUp">
                    <li><a href="{{ \App\Support\Toko::url('/') }}">Home</a></li>
                    <li class="active">{{ content('about.text.2', 'About Us') }}</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="sw-overlay"></div>
</section>


    <section>
        <div class="container">
            <div class="row gy-4 gx-5 align-items-center">
                <div class="col-lg-6">
                    <div class="relative">
                        <img src="{{ content_image('about.image.2', 'assets/images/about/p3.webp') }}"
                            class="relative z-2 mb-5 rounded-1 w-60 soft-shadow" alt="">
                        <img src="{{ content_image('about.image.3', 'assets/images/about/p2.webp') }}" class="abs end-0 mt-5 rounded-1 mb-4 w-60"
                            alt="">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="subtitle s2 mb-3 wow fadeInUp" data-wow-delay=".0s">About Us</div>
                    <h2 class="wow fadeInUp" data-wow-delay=".2s">
                        {{ content('about.text.3', 'HYPER SONIC SPEED TECH') }}
                    </h2>
                    <p>
                        {{ content('about.text.4', 'Founded in early 2025, HYPER SONIC SPEED TECH was born from a passion for power, precision, and performance.
                        We specialize in the production and distribution of premium exhaust systems for Harley-Davidson and
                        large-displacement motorcycles. Our mission is to deliver products that not only enhance performance
                        but also reflect the spirit of freedom and prestige that these machines embody.') }}
                    </p>
                    <p>
                        {{ content('about.text.5', 'At HYPER SONIC SPEED TECH, quality is our foundation. Every exhaust is crafted from superior-grade materials,
                        ensuring durability, flawless finish, and unmatched performance.
                        Our dedication to excellence allows us to deliver products that meet the highest standards—designed
                        to endure, inspire, and perform. One of our key strengths lies in unmatched availability.
                        With an extensive stock always on hand, we guarantee that every order is ready to ship without delay.
                        Whether you are an enthusiast or a dealer, our commitment ensures that you receive your products quickly,
                        without compromise.') }}
                    </p>
                    <p>
                        {{ content('about.text.6', 'Beyond quality and availability, our company prides itself on productivity and precision engineering.
                        Every step of our production process reflects efficiency, innovation, and craftsmanship.
                        This allows us to stay ahead in meeting the demands of the market,
                        while maintaining the exclusivity and high-class appeal that define our brand.
                        At HYPER SONIC SPEED TECH, we don’t just manufacture exhausts—we create statements of performance and style.
                        With us, speed, sound, and technology converge into one seamless experience, setting new benchmarks for motorcycle enthusiasts worldwide.') }}
                    </p>
                </div>
            </div>
        </div>
    </section>
        </div>
@endsection
