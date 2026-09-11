@extends('layouts.app')

@section('title', "Contact")

@section('content')
<div class="no-bottom no-top" id="content">
            <div id="top"></div>
                <section class="bg-dark text-light relative jarallax">
    <div class="de-gradient-edge-top"></div>
    <img src="{{ content_image('contact.image.1', 'assets/images/2345.png') }}" class="jarallax-img" alt="">
    <div class="container relative z-2">
        <div class="row gy-4 gx-5 justify-content-center">
            <div class="col-lg-12 text-center">
                <div class="spacer-double sm-hide"></div>
                <h1 class="mb-3 wow fadeInUp" data-wow-delay=".2s">
                    {{ content('contact.text.1', 'Contact Us') }}
                </h1>
                <div class="border-bottom mb-3"></div>
                <ul class="crumb wow fadeInUp">
                    <li><a href="{{ \App\Support\Toko::url('/') }}">Home</a></li>
                    <li class="active">{{ content('contact.text.2', 'Contact Us') }}</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="sw-overlay"></div>
</section>

    <section>
        <div class="container">
            <div class="row g-4 justify-content-between">
                <div class="col-lg-6">
                    <div class="p-40 h-100 jarallax rounded-1 overflow-hidden">
                        <img src="{{ content_image('contact.image.2', 'assets/images/bg/bg.jpg') }}" class="jarallax-img" alt="">
                        <div class="sw-overlay"></div>
                        <div class="gradient-edge-bottom h-80"></div>
                        <div class="relative z-2">
                            <div class="subtitle">Get In Touch</div>
                            <h2 class="wow fadeInUp">{{ content('contact.text.3', 'We are always ready to help you') }}</h2>

                            <p>{!! content('contact.text.4', 'Whether you have a question, a suggestion, or just want to say hello, this is the place to do
                                it. Please fill out the form below with your details and message, and we\'ll get back to you
                                as soon as possible.') !!}</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="p-40 bg-dark-2 text-light rounded-1">
                        <h3>{{ content('contact.text.5', 'Get In Touch') }}</h3>
                        <form name="contactForm" id="contact_form" class="relative z1000" method="post" action="#">

                            <div class="field-set">
                                <input type="text" name="name" id="name" class="form-control"
                                    placeholder="Your Name" required>
                            </div>

                            <div class="field-set">
                                <input type="text" name="email" id="email" class="form-control"
                                    placeholder="Your Email" required>
                            </div>

                            <div class="field-set">
                                <input type="text" name="phone" id="phone" class="form-control"
                                    placeholder="Your Phone" required>
                            </div>

                            <div class="field-set mb20">
                                <textarea name="message" id="message" class="form-control" placeholder="Your Message" required></textarea>
                            </div>


                            <div id='submit' class="mt20">
                                <input type='submit' id='send_message' value='Send Message' class="btn-main">
                            </div>

                            <div id="success_message" class='success'>
                                Your message has been sent successfully. Refresh this page if you want to send more
                                messages.
                            </div>
                            <div id="error_message" class='error'>
                                Sorry there was an error sending your form.
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>
        </div>
@endsection
