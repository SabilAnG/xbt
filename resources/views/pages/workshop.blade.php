@extends('layouts.app')

@section('title', "Workshop")

@section('content')
<div class="no-bottom no-top" id="content">
            <div id="top"></div>
                <section class="bg-dark text-light relative jarallax">
    <div class="de-gradient-edge-top"></div>
    <img src="{{ content_image('workshop.image.1', 'assets/images/2345.png') }}" class="jarallax-img" alt="">
    <div class="container relative z-2">
        <div class="row gy-4 gx-5 justify-content-center">
            <div class="col-lg-12 text-center">
                <div class="spacer-double sm-hide"></div>
                <h1 class="mb-3 wow fadeInUp" data-wow-delay=".2s">
                    {{ content('workshop.text.1', 'Workshop') }}
                </h1>
                <div class="border-bottom mb-3"></div>
                <ul class="crumb wow fadeInUp">
                    <li><a href="{{ \App\Support\Toko::url('/') }}">Home</a></li>
                    <li class="active">{{ content('workshop.text.2', 'Workshop') }}</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="sw-overlay"></div>
</section>

    <section>
        <div class="container">
            <div class="row g-4">

                                    <div class="col-lg-4 col-sm-6">
                        <div class="hover">
                            <div class="relative overflow-hidden">
                                <div class="relative overflow-hidden">
                                    <img src="{{ content_image('workshop.image.2', 'storage/workshop1.jpg') }}" class="w-100 hover-scale-1-2 rounded-1"
                                        style="width:100%; height:440px; object-fit:cover; border-radius:8px;"
                                        onerror="this.src = '{{ content_image('workshop.image.3', 'assets/images/workshop/fallback-1000x1000.webp') }}'"
                                        alt="">

                                </div>
                                <div class="p-30 relative bg-dark-2 mx-4 mt-min-100 rounded-1">
                                    <h4>{{ content('workshop.text.3', 'Budi Santoso') }}</h4>
                                    <i
                                        class="icofont-user me-2 id-color"></i><span>{{ content('workshop.text.4', 'Kepala Mekanik') }}</span><br>
                                    <i
                                        class="icofont-pin me-2 id-color"></i><span>{{ content('workshop.text.5', 'Jakarta') }}</span><br>

                                    <a class="btn-main fx-slide mt-3 w-100" href="tel:081234567890">
                                        <span>{{ content('workshop.text.6', 'Contact Person') }}</span>
                                    </a>

                                                                            <a class="btn-main fx-slide mt-1 w-100" target="_blank"
                                            href="/redirect/away?to=https%3A%2F%2Fwa.me%2F6281234567890&amp;utm_source=web_workshop_page">
                                            <span>{{ content('workshop.text.7', 'WhatsApp Message') }}</span>
                                        </a>
                                                                    </div>
                            </div>
                        </div>
                    </div>
                                    <div class="col-lg-4 col-sm-6">
                        <div class="hover">
                            <div class="relative overflow-hidden">
                                <div class="relative overflow-hidden">
                                    <img src="{{ content_image('workshop.image.4', 'storage/workshop2.jpg') }}" class="w-100 hover-scale-1-2 rounded-1"
                                        style="width:100%; height:440px; object-fit:cover; border-radius:8px;"
                                        onerror="this.src = '{{ content_image('workshop.image.5', 'assets/images/workshop/fallback-1000x1000.webp') }}'"
                                        alt="">

                                </div>
                                <div class="p-30 relative bg-dark-2 mx-4 mt-min-100 rounded-1">
                                    <h4>{{ content('workshop.text.8', 'Siti Aminah') }}</h4>
                                    <i
                                        class="icofont-user me-2 id-color"></i><span>{{ content('workshop.text.9', 'Manajer Bengkel') }}</span><br>
                                    <i
                                        class="icofont-pin me-2 id-color"></i><span>{{ content('workshop.text.10', 'Bandung') }}</span><br>

                                    <a class="btn-main fx-slide mt-3 w-100" href="tel:082345678901">
                                        <span>{{ content('workshop.text.11', 'Contact Person') }}</span>
                                    </a>

                                                                            <a class="btn-main fx-slide mt-1 w-100" target="_blank"
                                            href="/redirect/away?to=https%3A%2F%2Fwa.me%2F6282345678901&amp;utm_source=web_workshop_page">
                                            <span>{{ content('workshop.text.12', 'WhatsApp Message') }}</span>
                                        </a>
                                                                    </div>
                            </div>
                        </div>
                    </div>
                                    <div class="col-lg-4 col-sm-6">
                        <div class="hover">
                            <div class="relative overflow-hidden">
                                <div class="relative overflow-hidden">
                                    <img src="{{ content_image('workshop.image.6', 'storage/workshop3.jpg') }}" class="w-100 hover-scale-1-2 rounded-1"
                                        style="width:100%; height:440px; object-fit:cover; border-radius:8px;"
                                        onerror="this.src = '{{ content_image('workshop.image.7', 'assets/images/workshop/fallback-1000x1000.webp') }}'"
                                        alt="">

                                </div>
                                <div class="p-30 relative bg-dark-2 mx-4 mt-min-100 rounded-1">
                                    <h4>{{ content('workshop.text.13', 'Andi Wijaya') }}</h4>
                                    <i
                                        class="icofont-user me-2 id-color"></i><span>{{ content('workshop.text.14', 'Mekanik Senior') }}</span><br>
                                    <i
                                        class="icofont-pin me-2 id-color"></i><span>{{ content('workshop.text.15', 'Surabaya') }}</span><br>

                                    <a class="btn-main fx-slide mt-3 w-100" href="tel:083456789012">
                                        <span>{{ content('workshop.text.16', 'Contact Person') }}</span>
                                    </a>

                                                                            <a class="btn-main fx-slide mt-1 w-100" target="_blank"
                                            href="/redirect/away?to=https%3A%2F%2Fwa.me%2F6283456789012&amp;utm_source=web_workshop_page">
                                            <span>{{ content('workshop.text.17', 'WhatsApp Message') }}</span>
                                        </a>
                                                                    </div>
                            </div>
                        </div>
                    </div>
                
            </div>
        </div>
    </section>

    <section>
        <div class="container">
            <div class="row gy-4 gx-5 align-items-center">
                <div class="col-lg-6">
                    <div class="p-40 h-100 jarallax rounded-1 overflow-hidden">
                        <img src="{{ content_image('workshop.image.8', 'assets/images/bg/bg.jpg') }}"
                            class="jarallax-img" alt="">
                        <div class="sw-overlay"></div>
                        <div class="gradient-edge-bottom h-80"></div>
                        <div class="relative z-2">
                            <div class="subtitle">Get In Touch</div>
                            <h2 class="wow fadeInUp">{{ content('workshop.text.18', 'We are always ready to help you') }}</h2>

                            <p>{!! content('workshop.text.19', 'Whether you have a question, a suggestion, or just want to say hello, this is the place to do
                                it. Please fill out the form below with your details and message, and we\'ll get back to you
                                as soon as possible.') !!}</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h2 class="wow fadeInUp" data-wow-delay=".2s">
                        {!! content('workshop.text.20', 'Exhaust Specialist for Power & Performance') !!}
                    </h2>
                    <p>{{ content('workshop.text.21', 'At Hypersonic Tech Speed, we are passionate about engineering high-performance exhaust systems that
                        elevate driving experiences. Since our beginning, we’ve committed ourselves to precision
                        craftsmanship, using premium materials and advanced technology to deliver exhaust solutions that
                        maximize power, sound, and durability.') }}</p>

                    <p>{{ content('workshop.text.22', 'We believe performance upgrades should be powerful and reliable. That’s why we design and manufacture
                        precision exhaust systems, from daily-driver solutions to high-performance racing setups-each
                        tailored to your vehicle’s needs. With a focus on innovation, durability, and driving satisfaction,
                        Hypersonic Tech Speed is your trusted partner in maximizing power, sound, and style on every
                        journey.') }}</p>
                </div>
            </div>
        </div>
    </section>
        </div>
@endsection
