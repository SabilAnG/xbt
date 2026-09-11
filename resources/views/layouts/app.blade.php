<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/swiper.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/colors/scheme-1.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/custom-swiper-1.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}">
        @stack('head')
        <script>
        window.__redirect = function(path) {
            window.location.href = "{{ url('/') }}" + path;
        }
    </script>
</head>

<body class="dark-scheme">
    <div id="wrapper">
        <a href="#" id="back-to-top"></a>

        <div id="de-loader"></div>

        
@include('partials.header')

@yield('content')

@include('partials.footer')

    </div>

    <div id="extra-wrap" class="text-light">
    <div id="btn-close">
        <span></span>
        <span></span>
    </div>

    <div id="extra-content">
                    <img src="{{ asset('assets/images/logo-white.png') }}" class="w-150px" alt="" />
                

        <div class="spacer-30-line"></div>

        <h5>Contact Us</h5>
        <div>
            <i class="icofont-clock-time me-2 op-5"></i>
        </div>
        <div>
            <i class="icofont-location-pin me-2 op-5"></i>Jl. Raya Rabak No. 1
RT 01 / RW 05, Kalimanah
Purbalingga, Central Java 53371
Indonesia
        </div>
        <div>
            <i class="icofont-envelope me-2 op-5"></i><a href="mailto:{{ setting_email() }}">{{ setting_email() }}</a>
        </div>

        <div class="spacer-30-line"></div>

        <h5>About Us</h5>
        <p>
            At Hypersonic Tech Speed, we are passionate about engineering high-performance exhaust systems that
            elevate driving experiences. Since our beginning, we’ve committed ourselves to precision
            craftsmanship, using premium materials and advanced technology to deliver exhaust solutions that
            maximize power, sound, and durability.
        </p>

        <div class="social-icons">
            
            <a target="_blank" href="/redirect/away?to=https%3A%2F%2Fwww.facebook.com%2Fshare%2F17kUNcDAyq%2F&amp;utm_source=web_overlay_menu">
                <i class="fa-brands fa-facebook-f"></i>
            </a>
            <a target="_blank" href="#">
                <i class="fa-brands fa-tiktok"></i>
            </a>
            <a target="_blank" href="/redirect/away?to=https%3A%2F%2Fwww.instagram.com%2Fhypersonic_speedtech%3Figsh%3DZGwyZDh0azVpcm00&amp;utm_source=web_overlay_menu">
                <i class="fa-brands fa-instagram"></i>
            </a>
            <a target="_blank" href="#">
                <i class="fa-brands fa-youtube"></i>
            </a>
            <a target="_blank" href="/redirect/away?to=https%3A%2F%2Fwa.me%2F{{ setting_wa() }}%3Ftext%3DHello%2BHypersonic%2BSpeed%2BTech&amp;utm_source=web_overlay_menu">
                <i class="fa-brands fa-whatsapp"></i>
            </a>
        </div>
    </div>
</div>


<script src="{{ asset('assets/js/plugins.js') }}"></script>
<script src="{{ asset('assets/js/swiper.js') }}"></script>
<script src="{{ asset('assets/js/custom-swiper-3.js') }}"></script>
<script src="{{ asset('assets/js/jquery.event.move.js') }}"></script>
<script src="{{ asset('assets/js/jquery.twentytwenty.js') }}"></script>
<script src="{{ asset('assets/js/script.js') }}"></script>
<script>
        $(window).on("load", function() {
            $(".twentytwenty-container[data-orientation!='vertical']").twentytwenty({
                default_offset_pct: 0.5
            });

            $(".twentytwenty-container[data-orientation='vertical']").twentytwenty({
                default_offset_pct: 0.5,
                orientation: "vertical",
            });
        });
    </script>
@stack('scripts')
</body>

</html>
