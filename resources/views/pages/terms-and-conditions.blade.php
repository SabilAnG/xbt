@extends('layouts.app')

@section('title', "Terms & Conditions")

@section('content')
<div class="no-bottom no-top" id="content">
            <div id="top"></div>
                <section class="bg-dark text-light relative jarallax">
    <div class="de-gradient-edge-top"></div>
    <img src="{{ content_image('terms-and-conditions.image.1', 'assets/images/2345.png') }}" class="jarallax-img" alt="">
    <div class="container relative z-2">
        <div class="row gy-4 gx-5 justify-content-center">
            <div class="col-lg-12 text-center">
                <div class="spacer-double sm-hide"></div>
                <h1 class="mb-3 wow fadeInUp" data-wow-delay=".2s">
                    {!! content('terms-and-conditions.text.1', 'Terms &amp; Conditions') !!}
                </h1>
                <div class="border-bottom mb-3"></div>
                <ul class="crumb wow fadeInUp">
                    <li><a href="/">Home</a></li>
                    <li class="active">{!! content('terms-and-conditions.text.2', 'Terms &amp; Conditions') !!}</li>
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

                    <!-- Terms & Conditions -->
                    <div class="terms-card bg-dark text-white p-5 rounded-4 shadow-lg mb-5">
                        <h2 class="mb-4 text-orange fw-bold border-bottom border-3 border-orange d-inline-block pb-2">
                            {!! content('terms-and-conditions.text.3', 'Terms & Conditions') !!}
                        </h2>
                        <p class="mb-4">
                            Welcome to <span class="fw-bold text-orange">{{ content('terms-and-conditions.text.4', 'Hypersonic.id') }}</span>. 
                            By accessing and using our services, you acknowledge that you have read, understood, 
                            and agreed to the following Terms & Conditions:
                        </p>

                        <h5 class="mt-4 text-orange">{{ content('terms-and-conditions.text.5', '1. Website Usage') }}</h5>
                        <p>
                            {{ content('terms-and-conditions.text.6', 'You agree to use this website solely for lawful purposes and in a way that does not infringe 
                            on the rights of, restrict, or inhibit anyone else’s use of the website.') }}
                        </p>

                        <h5 class="mt-4 text-orange">{{ content('terms-and-conditions.text.7', '2. Product Information') }}</h5>
                        <p>
                            {{ content('terms-and-conditions.text.8', 'We make every effort to provide accurate product details. However, there may be slight differences 
                            in color, specifications, or stock availability due to production updates or system limitations.') }}
                        </p>

                        <h5 class="mt-4 text-orange">{!! content('terms-and-conditions.text.9', '3. Orders & Payments') !!}</h5>
                        <p>
                            {{ content('terms-and-conditions.text.10', 'All orders are considered valid once payment is verified. 
                            We reserve the right to cancel transactions in cases of pricing errors or suspected fraud.') }}
                        </p>

                        <h5 class="mt-4 text-orange">{{ content('terms-and-conditions.text.11', '4. Shipping') }}</h5>
                        <p>
                            {{ content('terms-and-conditions.text.12', 'Delivery times may vary depending on courier services and destination. 
                            We are not responsible for delays caused by third-party logistics providers.') }}
                        </p>

                        <h5 class="mt-4 text-orange">{{ content('terms-and-conditions.text.13', '5. Warranty Policy') }}</h5>
                        <p>
                            {{ content('terms-and-conditions.text.14', 'Hypersonic.id products come with an official warranty. 
                            Warranty claims are only valid for manufacturing defects and do not cover damages caused 
                            by misuse or improper handling.') }}
                        </p>

                        <h5 class="mt-4 text-orange">{{ content('terms-and-conditions.text.15', '6. Amendments') }}</h5>
                        <p>
                            {!! content('terms-and-conditions.text.16', 'Hypersonic.id reserves the right to update or revise these Terms & Conditions at any time. 
                            Any changes will be published on our official website.') !!}
                        </p>

                        <p class="mt-5 fst-italic text-muted">
                            If you have any questions regarding these Terms & Conditions, 
                            please contact us via our 
                            <a href="/contact" class="text-orange fw-bold">Contact Page</a>.
                        </p>
                    </div>

                    <!-- FAQ Section -->
                    <div class="faq-card bg-dark-2 text-white p-5 rounded-4 shadow-lg">
                        <h2 class="mb-4 fw-bold text-orange border-bottom border-3 border-orange d-inline-block pb-2">
                            <i class="fas fa-question-circle me-2"></i> Frequently Asked Questions (FAQ)
                        </h2>

                        <div class="accordion" id="faqAccordion">

                            <!-- Refund -->
                            <div class="accordion-item bg-dark border-0 mb-3 rounded-3 shadow-sm">
                                <h2 class="accordion-header" id="faqOne">
                                    <button class="accordion-button bg-dark text-white fw-bold collapsed border-start border-4 border-orange" 
                                        type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                        <i class="fas fa-wallet text-orange me-2"></i> How can I request a refund?
                                    </button>
                                </h2>
                                <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body text-gray-300">
                                        Refunds are only available for defective or damaged products. 
                                        Please contact our support team within <span class="fw-bold text-orange">{{ content('terms-and-conditions.text.17', '7 days') }}</span> of receiving your order. 
                                    </div>
                                </div>
                            </div>

                            <!-- Returns -->
                            <div class="accordion-item bg-dark border-0 mb-3 rounded-3 shadow-sm">
                                <h2 class="accordion-header" id="faqTwo">
                                    <button class="accordion-button bg-dark text-white fw-bold collapsed border-start border-4 border-orange"
                                        type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                        <i class="fas fa-undo-alt text-orange me-2"></i> Can I return my product?
                                    </button>
                                </h2>
                                <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body text-gray-300">
                                        Yes, returns are accepted if the product is unused and in its original packaging. 
                                        Return requests must be submitted within <span class="fw-bold text-orange">{{ content('terms-and-conditions.text.18', '7 days') }}</span> of delivery.
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Tracking -->
                            <div class="accordion-item bg-dark border-0 mb-3 rounded-3 shadow-sm">
                                <h2 class="accordion-header" id="faqThree">
                                    <button class="accordion-button bg-dark text-white fw-bold collapsed border-start border-4 border-orange"
                                        type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                        <i class="fas fa-truck text-orange me-2"></i> How can I track my order?
                                    </button>
                                </h2>
                                <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body text-gray-300">
                                        Once your order has been shipped, you will receive a tracking number via email or WhatsApp. 
                                        You can use it to track your shipment directly on the courier’s website.
                                    </div>
                                </div>
                            </div>

                            <!-- Warranty -->
                            <div class="accordion-item bg-dark border-0 mb-3 rounded-3 shadow-sm">
                                <h2 class="accordion-header" id="faqFour">
                                    <button class="accordion-button bg-dark text-white fw-bold collapsed border-start border-4 border-orange"
                                        type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour">
                                        <i class="fas fa-shield-alt text-orange me-2"></i> Does my product have a warranty?
                                    </button>
                                </h2>
                                <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body text-gray-300">
                                        Yes, all Hypersonic products include a warranty for manufacturing defects. 
                                        Please check the warranty section above for details.
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
        </div>
@endsection
