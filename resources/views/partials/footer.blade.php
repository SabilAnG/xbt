@include('partials.iklan', ['posisi' => 'footer'])

<footer>
    <div class="container">
        <div class="row gx-5">
            <div class="col-lg-4 col-sm-12 order-lg-1 order-sm-2">
                <div class="footer-map">
                                                                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d249.2188320162923!2d109.34325189643208!3d-7.43163651232375!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e655a26da27b115%3A0x5027a76e35517e0!2sRabak%2C%20Kec.%20Kalimanah%2C%20Kabupaten%20Purbalingga%2C%20Jawa%20Tengah!5e0!3m2!1sid!2sid!4v1761767021044!5m2!1sid!2sid" width="100%" height="250"
                            style="border:0; border-radius: 8px;" allowfullscreen="" loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                                    </div>
            </div>

            <div class="col-lg-4 col-sm-12 order-lg-1 order-sm-2">
                <div class="row">
                    <div class="col-lg-5">
                        <div class="widget">
                            <h5>{{ content('footer.text.1', 'Company') }}</h5>
                            <ul>
                                <li>
                                    <a href="{{ \App\Support\Toko::url('/') }}">Home</a>
                                </li>
                                <li>
                                    <a href="{{ \App\Support\Toko::url('/products') }}">Products</a>
                                </li>
                                <li>
                                    <a href="{{ \App\Support\Toko::url('/workshop') }}">Workshop</a>
                                </li>
                                <li>
                                    <a href="{{ \App\Support\Toko::url('/about') }}">About</a>
                                </li>
                                <li>
                                    <a href="{{ \App\Support\Toko::url('/contact') }}">Contact</a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="widget">
                            <h5>{{ content('footer.text.2', 'Our Social Media') }}</h5>
                                                        <ul>
                                <li>
                                    <a target="_blank"
                                        href="/redirect/away?to=https%3A%2F%2Fwww.facebook.com%2Fshare%2F17kUNcDAyq%2F&amp;utm_source=web_footer_menu">
                                        Facebook
                                    </a>
                                </li>
                                <li>
                                    <a target="_blank"
                                        href="/redirect/away?to=https%3A%2F%2Fwww.instagram.com%2Fhypersonic_speedtech%3Figsh%3DZGwyZDh0azVpcm00&amp;utm_source=web_footer_menu">
                                        Instagram
                                    </a>
                                </li>
                                <li>
                                    <a target="_blank"
                                        href="#">
                                        TikTok
                                    </a>
                                </li>
                                <li>
                                                                        <a target="_blank" href="/redirect/away?to=https%3A%2F%2Fwa.me%2F62895337161221%3Ftext%3DHello%2BHypersonic%2BSpeed%2BTech&amp;utm_source=web_footer_menu">
                                        WhatsApp
                                    </a>
                                </li>
                                <li>
                                    <a target="_blank"
                                        href="#">
                                        YouTube
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-sm-6 order-lg-2 order-sm-1">
                <div class="widget">
                    <h5>{{ content('footer.text.3', 'Contact Us') }}</h5>

                    <div class="fw-bold text-white">
                        <i class="icofont-location-pin me-2 id-color"></i>Head Office
                    </div>
                    Jl. Raya Rabak No. 1
RT 01 / RW 05, Kalimanah
Purbalingga, Central Java 53371
Indonesia

                    <div class="spacer-20"></div>

                    <div class="fw-bold text-white">
                        <i class="icofont-phone me-2 id-color"></i>Call Us
                    </div>
                    62895337161221

                    <div class="spacer-20"></div>

                    <div class="fw-bold text-white">
                        <i class="icofont-envelope me-2 id-color"></i>Email Us
                    </div>
                    <a href="mailto:hypersonicspeedtech@gmail.com">hypersonicspeedtech@gmail.com</a>
                </div>
            </div>
        </div>
    </div>

    <div class="subfooter">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="de-flex">
                        <div class="de-flex-col">
                            Copyright &copy; 2026 &nbsp;<a href="{{ \App\Support\Toko::url('/') }}"
                                class="text-orange fw-bold text-decoration-none">Hypersonic Speed Tech</a>
                        </div>
                        <ul class="menu-simple">
                            <li>
                                <a href="{{ \App\Support\Toko::url('/terms-and-conditions') }}">Terms &amp; Conditions</a>
                            </li>
                            <li>
                                <a href="{{ \App\Support\Toko::url('/privacy-policy') }}">Privacy Policy</a>
                            </li>
                            @unless (\App\Support\HakPartner::partner())
                                <li>
                                    <a href="/pasang-iklan">Pasang Iklan</a>
                                </li>
                            @endunless
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
