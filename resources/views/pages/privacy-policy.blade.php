@extends('layouts.app')

@section('title', "Privacy Policy")

@section('content')
<div class="no-bottom no-top" id="content">
            <div id="top"></div>
                <section class="bg-dark text-light relative jarallax">
    <div class="de-gradient-edge-top"></div>
    <img src="{{ content_image('privacy-policy.image.1', 'assets/images/2345.png') }}" class="jarallax-img" alt="">
    <div class="container relative z-2">
        <div class="row gy-4 gx-5 justify-content-center">
            <div class="col-lg-12 text-center">
                <div class="spacer-double sm-hide"></div>
                <h1 class="mb-3 wow fadeInUp" data-wow-delay=".2s">
                    {{ content('privacy-policy.text.1', 'Privacy Policy') }}
                </h1>
                <div class="border-bottom mb-3"></div>
                <ul class="crumb wow fadeInUp">
                    <li><a href="/">Home</a></li>
                    <li class="active">{{ content('privacy-policy.text.2', 'Privacy Policy') }}</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="sw-overlay"></div>
</section>

    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-12">

                    <!-- Privacy Policy -->
                    <div class="privacy-card bg-dark text-white p-5 rounded-4 shadow-lg mb-5">
                        <h2 class="mb-4 text-orange fw-bold border-bottom border-3 border-orange d-inline-block pb-2">
                            {{ content('privacy-policy.text.3', 'Privacy Policy') }}
                        </h2>
                        <p class="mb-4">
                            At <span class="fw-bold text-orange">{{ content('privacy-policy.text.4', 'Hypersonic.id') }}</span>, we respect your privacy and are committed 
                            to protecting your personal data. This Privacy Policy explains how we collect, use, and safeguard 
                            your information when you visit our website or purchase our products.
                        </p>

                        <h5 class="mt-4 text-orange">{{ content('privacy-policy.text.5', '1. Information We Collect') }}</h5>
                        <p>
                            {{ content('privacy-policy.text.6', 'We may collect personal information such as your name, email address, phone number, shipping address, 
                            and payment details when you create an account, place an order, or subscribe to our newsletter.') }}
                        </p>

                        <h5 class="mt-4 text-orange">{{ content('privacy-policy.text.7', '2. How We Use Your Information') }}</h5>
                        <p>
                            {{ content('privacy-policy.text.8', 'The information we collect is used to process your orders, provide customer support, 
                            improve our services, and send you promotional offers (if you opt-in).') }}
                        </p>

                        <h5 class="mt-4 text-orange">{!! content('privacy-policy.text.9', '3. Cookies & Tracking') !!}</h5>
                        <p>
                            {{ content('privacy-policy.text.10', 'Our website uses cookies and similar technologies to enhance your browsing experience, 
                            analyze traffic, and personalize content. You can disable cookies in your browser settings, 
                            but some features may not function properly.') }}
                        </p>

                        <h5 class="mt-4 text-orange">{{ content('privacy-policy.text.11', '4. Data Security') }}</h5>
                        <p>
                            {{ content('privacy-policy.text.12', 'We implement industry-standard security measures to protect your personal data from unauthorized 
                            access, alteration, or disclosure. However, no method of transmission over the Internet is 100% secure.') }}
                        </p>

                        <h5 class="mt-4 text-orange">{{ content('privacy-policy.text.13', '5. Third-Party Services') }}</h5>
                        <p>
                            {{ content('privacy-policy.text.14', 'We may share limited information with trusted third-party providers such as payment gateways 
                            and shipping partners to complete your transactions. These providers are obligated to keep 
                            your information secure.') }}
                        </p>

                        <h5 class="mt-4 text-orange">{{ content('privacy-policy.text.15', '6. Your Rights') }}</h5>
                        <p>
                            You have the right to access, update, or request deletion of your personal data. 
                            To exercise these rights, please contact our support team at 
                            <a href="mailto:hypersonicspeedtech@gmail.com" class="text-orange fw-bold">support@hypersonic.id</a>.
                        </p>

                        <h5 class="mt-4 text-orange">{{ content('privacy-policy.text.16', '7. Policy Updates') }}</h5>
                        <p>
                            {{ content('privacy-policy.text.17', 'We may update this Privacy Policy from time to time. 
                            Any changes will be posted on this page with the updated revision date.') }}
                        </p>

                        <p class="mt-5 fst-italic text-muted">
                            By using our website, you agree to the terms of this Privacy Policy. 
                            If you have any concerns, please reach out via our 
                            <a href="/contact" class="text-orange fw-bold">Contact Page</a>.
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </section>
        </div>
@endsection
