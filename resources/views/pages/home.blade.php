@extends('layouts.app')

@section('title', "Welcome to Official Hypersonic Speed Tech")

@section('content')
<div class="no-bottom no-top" id="content">
            <div id="top"></div>
                <section class="text-light no-top no-bottom relative overflow-hidden">
        <div class="mh-700">
            <div class="abs w-100 bottom-0 z-2 pb-4">
                <div class="container">
                    <div class="row g-4 justify-content-between align-items-bottom">
                        <div class="col-lg-8">
                            <div class="sw-text-wrapper">
                                <div class="subtitle wow fadeInUp">
                                    Professional Exhaust Full System
                                </div>
                                <h1 class="fs-72 fs-xs-10vw text-uppercase wow fadeInUp">
                                    HYPERSONIC
                                    <span class="id-color">{{ content('home.text.1', 'SPEED') }}</span>
                                    TECH
                                </h1>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="spacer-double"></div>
                            <p class="mb-0 wow fadeInUp" data-wow-delay=".2s">
                                {{ content('home.text.2', 'At HST, we deliver reliable,
                                efficient detailing for motorcycle exhausts,
                                enhancing performance, restoring lasting shine,
                                extending durability, and protecting your ride’s value.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="swiper">
                <!-- Additional required wrapper -->
                <div class="swiper-wrapper">
                    <!-- Slides -->
                    <div class="swiper-slide">
                        <div class="swiper-inner" data-bgimage="url(assets/images/header1.png)">
                            <div class="gradient-edge-top h-20 op-5"></div>
                            <div class="gradient-edge-bottom h-50"></div>
                            <div class="sw-overlay op-6"></div>
                        </div>
                    </div>

                    <!-- Slides -->
                    <div class="swiper-slide">
                        <div class="swiper-inner" data-bgimage="url(assets/images/header2.png)">
                            <div class="gradient-edge-top h-20 op-5"></div>
                            <div class="gradient-edge-bottom h-50"></div>
                            <div class="sw-overlay op-6"></div>
                        </div>
                    </div>

                    <!-- Slides -->
                    <div class="swiper-slide">
                        <div class="swiper-inner" data-bgimage="url(assets/images/header3.png)">
                            <div class="gradient-edge-top h-20 op-5"></div>
                            <div class="gradient-edge-bottom h-50"></div>
                            <div class="sw-overlay op-6"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pb-0">
        <div class="container relative z-1">
            <div class="row g-4 gx-5 align-items-center">
                <div class="col-lg-6">
                    <div class="row g-4">
                        <div class="col-sm-6">
                            <div class="row g-4">
                                <div class="col-lg-12">
                                    <div class="rounded-1 overflow-hidden wow zoomIn">
                                        <img src="assets/images/about1.png" class="w-100 wow scaleIn" alt="" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="row g-4">
                                <div class="spacer-single sm-hide"></div>

                                <div class="col-lg-12">
                                    <div class="rounded-1 overflow-hidden wow zoomIn" data-wow-delay=".3s">
                                        <img src="assets/images/about2.png" class="w-100 wow scaleIn" alt=""
                                            data-wow-delay=".3s" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="subtitle wow fadeInUp" data-wow-delay=".2s">
                        About Us
                    </div>
                    <h2 class="wow fadeInUp" data-wow-delay=".4s">
                        {{ content('home.text.3', 'HYPER SONIC SPEED TECH') }}
                    </h2>
                    <p class="wow fadeInUp" data-wow-delay=".6s">
                        {{ content('home.text.4', 'At HYPER SONIC SPEED TECH, quality is our foundation.
                        Every exhaust is crafted from superior-grade materials,
                        ensuring durability, flawless finish, and unmatched performance.
                        Our dedication to excellence allows us to deliver products
                        that meet the highest standards—designed to endure, inspire, and perform.') }}
                    </p>
                    <a class="btn-main fx-slide wow fadeInUp" href="/about" data-wow-delay=".6s">
                        <span>{{ content('home.text.5', 'Read More') }}</span>
                    </a>
                </div>
            </div>
        </div>
        </div>
    </section>

    <section class="pb-80 jarallax" aria-label="section">
        <img src="assets/images/header3.png" class="jarallax-img" alt="" />
        <div class="gradient-edge-top"></div>
        <div class="sw-overlay"></div>
    </section>

    <section class="bg-dark-2">
        <div class="container">
            <div class="row g-4 justify-content-center mb-2">
                <div class="col-lg-6">
                    <div class="text-center">
                        <div class="subtitle">
                            Welcome to HYPERSONIC SPEED TECH
                        </div>
                        <h2>{{ content('home.text.6', 'Premium Exhaust System') }}</h2>
                        <p>
                        </p>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                                    <div class="col-lg-4 col-sm-6">
                        <div class="hover rounded-1 overflow-hidden relative text-light text-center wow fadeInRight"
                            data-wow-delay=".0s">
                            <img src="{{ content_image('home.image.1', 'storage/17/conversions/01KANSR90RHTWZCQ0HDKZ1ZTDN-thumb.jpg') }}" class="hover-scale-1-1 w-100"
                                alt="HARLEY DAVIDSON TOURING SERIES 2-2 EXHAUST SYSTEM"
                                onerror="this.src = '{{ content_image('home.image.2', 'assets/images/workshop/fallback-1000x1000.webp') }}'" />
                            <div class="abs w-100 px-4 hover-op-1 z-4 hover-mt-40 abs-centered">
                                <div class="mb-3">
                                    Harley Davidson Touring Series
                                </div>
                                <a class="btn-main fx-slide" href="/products/harley-davidson-touring-series-2-2-exhaust-system">
                                    <span>{{ content('home.text.7', 'View Details') }}</span>
                                </a>
                            </div>
                            <div class="abs bg-blur z-2 top-0 w-100 h-100 hover-op-1"></div>
                            <div class="abs z-2 bottom-0 mb-3 w-100 text-center hover-op-0">
                                <h4 class="mb-3">
                                    {{ content('home.text.8', 'HARLEY DAVIDSON TOURING SERIES 2-2 EXHAUST SYSTEM') }}
                                </h4>
                            </div>
                            <div class="gradient-edge-bottom color abs w-100 h-40 bottom-0"></div>
                        </div>
                    </div>
                                    <div class="col-lg-4 col-sm-6">
                        <div class="hover rounded-1 overflow-hidden relative text-light text-center wow fadeInRight"
                            data-wow-delay=".0s">
                            <img src="{{ content_image('home.image.3', 'storage/14/conversions/01KANRF7VX9GB46SMNDAGQ7XQX-thumb.jpg') }}" class="hover-scale-1-1 w-100"
                                alt="HARLEY DAVIDSON DYNA  2-1 EXHAUST SYSTEM"
                                onerror="this.src = '{{ content_image('home.image.4', 'assets/images/workshop/fallback-1000x1000.webp') }}'" />
                            <div class="abs w-100 px-4 hover-op-1 z-4 hover-mt-40 abs-centered">
                                <div class="mb-3">
                                    fit for DYNA 1986 - 2020+
                                </div>
                                <a class="btn-main fx-slide" href="/products/harley-davidson-dyna-2-1-exhaust-system">
                                    <span>{{ content('home.text.9', 'View Details') }}</span>
                                </a>
                            </div>
                            <div class="abs bg-blur z-2 top-0 w-100 h-100 hover-op-1"></div>
                            <div class="abs z-2 bottom-0 mb-3 w-100 text-center hover-op-0">
                                <h4 class="mb-3">
                                    {{ content('home.text.10', 'HARLEY DAVIDSON DYNA  2-1 EXHAUST SYSTEM') }}
                                </h4>
                            </div>
                            <div class="gradient-edge-bottom color abs w-100 h-40 bottom-0"></div>
                        </div>
                    </div>
                                    <div class="col-lg-4 col-sm-6">
                        <div class="hover rounded-1 overflow-hidden relative text-light text-center wow fadeInRight"
                            data-wow-delay=".0s">
                            <img src="{{ content_image('home.image.5', 'storage/11/conversions/01KANQ5G6PRB5J96CQWBYBGXN5-thumb.jpg') }}" class="hover-scale-1-1 w-100"
                                alt="HARLEY DAVIDSON SPORTSTER HORN MODEL 2-2 EXHAUST SYSTEM"
                                onerror="this.src = '{{ content_image('home.image.6', 'assets/images/workshop/fallback-1000x1000.webp') }}'" />
                            <div class="abs w-100 px-4 hover-op-1 z-4 hover-mt-40 abs-centered">
                                <div class="mb-3">
                                    fit for sportster 48 | 1200 | iron 883
                                </div>
                                <a class="btn-main fx-slide" href="/products/harley-davidson-sportster-horn-model-2-2-exhaust-system">
                                    <span>{{ content('home.text.11', 'View Details') }}</span>
                                </a>
                            </div>
                            <div class="abs bg-blur z-2 top-0 w-100 h-100 hover-op-1"></div>
                            <div class="abs z-2 bottom-0 mb-3 w-100 text-center hover-op-0">
                                <h4 class="mb-3">
                                    {{ content('home.text.12', 'HARLEY DAVIDSON SPORTSTER HORN MODEL 2-2 EXHAUST SYSTEM') }}
                                </h4>
                            </div>
                            <div class="gradient-edge-bottom color abs w-100 h-40 bottom-0"></div>
                        </div>
                    </div>
                                    <div class="col-lg-4 col-sm-6">
                        <div class="hover rounded-1 overflow-hidden relative text-light text-center wow fadeInRight"
                            data-wow-delay=".0s">
                            <img src="{{ content_image('home.image.7', 'storage/8/conversions/01KANMSXW37ZCJPMH5GHFHECH3-thumb.jpg') }}" class="hover-scale-1-1 w-100"
                                alt="HARLEY DAVIDSON V-ROD 2-1 EXHAUST SYSTEM "
                                onerror="this.src = '{{ content_image('home.image.8', 'assets/images/workshop/fallback-1000x1000.webp') }}'" />
                            <div class="abs w-100 px-4 hover-op-1 z-4 hover-mt-40 abs-centered">
                                <div class="mb-3">
                                    fit for MUSCLE VRSCA | VRSCAW | VRSCE &amp; etc
                                </div>
                                <a class="btn-main fx-slide" href="/products/harley-davidson-v-rod-2-1-exhaust-system">
                                    <span>{{ content('home.text.13', 'View Details') }}</span>
                                </a>
                            </div>
                            <div class="abs bg-blur z-2 top-0 w-100 h-100 hover-op-1"></div>
                            <div class="abs z-2 bottom-0 mb-3 w-100 text-center hover-op-0">
                                <h4 class="mb-3">
                                    {{ content('home.text.14', 'HARLEY DAVIDSON V-ROD 2-1 EXHAUST SYSTEM') }} 
                                </h4>
                            </div>
                            <div class="gradient-edge-bottom color abs w-100 h-40 bottom-0"></div>
                        </div>
                    </div>
                                    <div class="col-lg-4 col-sm-6">
                        <div class="hover rounded-1 overflow-hidden relative text-light text-center wow fadeInRight"
                            data-wow-delay=".0s">
                            <img src="{{ content_image('home.image.9', 'storage/3/conversions/01KANG8PM10CC0K56KVZJ0BRJS-thumb.jpg') }}" class="hover-scale-1-1 w-100"
                                alt="HARLEY DAVIDSON SPORTSTER TWISTED MODEL 2-2 EXHAUST SYSTEM"
                                onerror="this.src = '{{ content_image('home.image.10', 'assets/images/workshop/fallback-1000x1000.webp') }}'" />
                            <div class="abs w-100 px-4 hover-op-1 z-4 hover-mt-40 abs-centered">
                                <div class="mb-3">
                                    fit for sportster 48 | 1200 | IRON 883
                                </div>
                                <a class="btn-main fx-slide" href="/products/harley-davidson-sportster-twisted-model-2-2-exhaust-system">
                                    <span>{{ content('home.text.15', 'View Details') }}</span>
                                </a>
                            </div>
                            <div class="abs bg-blur z-2 top-0 w-100 h-100 hover-op-1"></div>
                            <div class="abs z-2 bottom-0 mb-3 w-100 text-center hover-op-0">
                                <h4 class="mb-3">
                                    {{ content('home.text.16', 'HARLEY DAVIDSON SPORTSTER TWISTED MODEL 2-2 EXHAUST SYSTEM') }}
                                </h4>
                            </div>
                            <div class="gradient-edge-bottom color abs w-100 h-40 bottom-0"></div>
                        </div>
                    </div>
                            </div>
        </div>
    </section>

    <section>
        <div class="container">
            <div class="row g-4 gx-5 align-items-center">
                <div class="col-lg-6">
                    <div class="subtitle">Real Results</div>
                    <h2>
                        {!! content('home.text.17', 'Before & After: Exhaust Performance Transformations') !!}
                    </h2>
                    <p>
                        {{ content('home.text.18', 'Experience the dramatic difference a precision-engineered exhaust makes—unleashing deeper sound,
                        improved horsepower, and enhanced driving dynamics. From stock to performance, every upgrade counts,
                        and the results speak for themselves.') }}
                    </p>
                </div>

                <div class="col-lg-6">
                    <div class="twentytwenty-container rounded-1">
                        <img src="assets/images/2.png" alt="" class="img-responsive" />
                        <img src="assets/images/after.jpg" alt="" class="img-responsive" />
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-color text-light pt-60 pb-50">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-9">
                    <h3 class="mb-0 fs-32">
                        {{ content('home.text.19', 'See our portfolio company') }}
                    </h3>
                </div>
                <div class="col-lg-3 text-lg-end">
                    <a class="btn-main fx-slide btn-line" href="/workshop"><span>{{ content('home.text.20', 'Read
                            More') }}</span></a>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-dark text-light">
        <div class="container relative z-1">
            <div class="row g-4 justify-content-center">
                <div class="col-lg-6 text-center">
                    <div class="subtitle id-color">
                        Trusted &amp; Affordable
                    </div>
                    <h2>{{ content('home.text.21', 'Why Choose Our Exhaust Systems?') }}</h2>
                    <p>
                        {{ content('home.text.22', 'From daily driving to high-performance racing, we engineer exhaust solutions with precision,
                        innovation, and a commitment to unmatched quality.') }}
                    </p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="bg-dark-2 p-40 h-100 rounded-1">
                        <div class="relative wow fadeInUp">
                            <h4>{{ content('home.text.23', 'Expert Engineering') }}</h4>
                            <p class="mb-0">
                                {{ content('home.text.24', 'Our exhausts are designed by specialists with years of experience in performance tuning and
                                fabrication.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="bg-dark-2 p-40 h-100 rounded-1">
                        <div class="relative wow fadeInUp">
                            <h4>{{ content('home.text.25', 'Tailored Solutions') }}</h4>
                            <p class="mb-0">
                                {{ content('home.text.26', 'Options customized for different vehicles and driving styles — from street performance to
                                full racing setups.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="bg-dark-2 p-40 h-100 rounded-1">
                        <div class="relative wow fadeInUp">
                            <h4>{{ content('home.text.27', 'Competitive Pricing') }}</h4>
                            <p class="mb-0">
                                {{ content('home.text.28', 'Premium quality at fair, transparent prices — delivering maximum value without compromise.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="bg-dark-2 p-40 h-100 rounded-1">
                        <div class="relative wow fadeInUp">
                            <h4>{{ content('home.text.29', 'After-Sales Support') }}</h4>
                            <p class="mb-0">
                                {{ content('home.text.30', 'We provide installation guidance, tuning tips, and long-term support to ensure your exhaust
                                performs at its best.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="relative jarallax mh-500" aria-label="section">
        <div class="gradient-edge-top"></div>
        <div class="gradient-edge-bottom"></div>
        <img src="assets/images/header2.png" class="jarallax-img" alt="" />
    </section>

    <section>
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="subtitle id-color wow fadeInUp" data-wow-delay=".0s">
                        Everything You Need to Know
                    </div>
                    <h2 class="wow fadeInUp" data-wow-delay=".2s">
                        {{ content('home.text.31', 'Frequently Asked Questions') }}
                    </h2>
                </div>

                <div class="col-lg-7">
                    <div class="accordion s2 wow fadeInUp">
                        <div class="accordion-section">
                            <div class="accordion-section-title" data-tab="#accordion-a1">
                                What is a performance exhaust system?
                            </div>
                            <div class="accordion-section-content" id="accordion-a1">
                                A performance exhaust system is designed to improve your vehicle’s power output, enhance
                                exhaust flow, and deliver a more aggressive sound compared to stock systems.
                            </div>

                            <div class="accordion-section-title" data-tab="#accordion-a2">
                                How often should I upgrade or service my exhaust?
                            </div>
                            <div class="accordion-section-content" id="accordion-a2">
                                It depends on your driving style and vehicle use. For daily drivers, high-quality exhausts
                                can last for years with minimal maintenance. For racing or heavy-use vehicles, regular
                                inspection is recommended.
                            </div>

                            <div class="accordion-section-title" data-tab="#accordion-a3">
                                What’s included in a Hypersonic Tech Speed exhaust upgrade?
                            </div>
                            <div class="accordion-section-content" id="accordion-a3">
                                Our systems include precision-engineered headers, mufflers, pipes, and tips — all designed
                                to maximize flow, reduce backpressure, and deliver optimal performance and sound.
                            </div>

                            <div class="accordion-section-title" data-tab="#accordion-a4">
                                Will a new exhaust increase horsepower?
                            </div>
                            <div class="accordion-section-content" id="accordion-a4">
                                Yes. By improving exhaust flow, our systems can unlock noticeable horsepower and torque
                                gains, especially when paired with other performance upgrades.
                            </div>

                            <div class="accordion-section-title" data-tab="#accordion-a5">
                                How long does installation take?
                            </div>
                            <div class="accordion-section-content" id="accordion-a5">
                                Installation time varies depending on your vehicle and the system selected. On average,
                                professional installation can take 1–3 hours. We also provide guidance for workshops and DIY
                                installers.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-dark-2 pt-50 relative no-bottom">
        <div class="container relative z-2">
            <div class="row g-4">
                <div class="col-lg-8 offset-lg-2 mb-4 text-center">
                    <div class="subtitle id-color wow fadeInUp mb-3">
                        Our Profile
                    </div>
                    <h2 class="wow fadeInUp">@hypersonic
                        speed.tech</h2>
                </div>
            </div>
        </div>
    </section>
        </div>
@endsection
