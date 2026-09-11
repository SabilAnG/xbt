<header class="transparent">
    <div id="topbar">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="d-flex justify-content-end xs-hide">
                        <div class="d-flex">
                            <div class="topbar-widget me-5">
                                                                <a href="tel:{{ setting_wa() }}">
                                    <img src="{{ content_image('header.image.1', 'assets/images/misc/phone.svg') }}" class=""
                                        alt="" />{{ setting_wa() }}
                                </a>
                            </div>
                            <div class="topbar-widget">
                                                                <a href="mailto:{{ setting_email() }}">
                                    <img src="{{ content_image('header.image.2', 'assets/images/misc/envelope.svg') }}" class=""
                                        alt="" />{{ setting_email() }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="de-flex sm-pt10">
                    <div class="de-flex-col">
                        <!-- logo begin -->
                        <div id="logo">
                            <a href="{{ \App\Support\Toko::url('/') }}">
                                                                    <img class="logo-main" src="{{ asset(setting('site.logo', 'assets/images/logo-white.png')) }}"
                                        alt="" style="height: 50px; width: auto; object-fit: contain;" />
                                    <img class="logo-mobile" src="{{ asset(setting('site.logo', 'assets/images/logo-white.png')) }}"
                                        alt="" style="height: 40px" />
                                                            </a>
                        </div>
                        <!-- logo end -->
                    </div>
                    <div class="de-flex-col header-col-mid">
                        <!-- mainemenu begin -->
                        <ul id="mainmenu">
                            <li>
                                <a class="menu-item" href="{{ \App\Support\Toko::url('/') }}">Home</a>
                            </li>
                            <li>
                                <a class="menu-item" href="{{ \App\Support\Toko::url('/products') }}">Products</a>
                            </li>
                            <li>
                                <a class="menu-item" href="{{ \App\Support\Toko::url('/workshop') }}">Workshop</a>
                            </li>
                            <li>
                                <a class="menu-item" href="{{ \App\Support\Toko::url('/about') }}">About</a>
                            </li>
                            <li>
                                <a class="menu-item" href="{{ \App\Support\Toko::url('/contact') }}">Contact</a>
                            </li>
                            {{-- Hanya di situs induk: toko partner tidak menawarkan orang jadi partner. --}}
                            @unless (\App\Support\HakPartner::partner())
                                <li>
                                    <a class="menu-item" href="/jadi-partner">Jadi Partner</a>
                                </li>
                            @endunless
                            <li class="tracking-link-mobile" style="display: none">
                                <a href="{{ \App\Support\Toko::url('/tracking') }}"
                                    class="btn-tracking-mobile fx-slide hover-white d-inline-flex align-items-center py-3"
                                    style="font-size: 16px;">
                                    <i class="fas fa-shipping-fast me-2" style="font-size: 16px;"></i>
                                    <span>{{ content('header.text.1', 'Tracking') }}</span>
                                </a>
                            </li>
                        </ul>
                        <!-- mainmenu end -->
                    </div>
                    <div class="de-flex-col">
                        <div class="menu_side_area">
                            <a href="{{ \App\Support\Toko::url('/tracking') }}"
                                class="btn-main fx-slide hover-white d-inline-flex align-items-center px-3 py-2"
                                style="font-size: 14px;">
                                <i class="fas fa-shipping-fast me-2" style="font-size: 16px;"></i>
                                <span>{{ content('header.text.2', 'Tracking') }}</span>
                            </a>
                            <span id="menu-btn"></span>
                        </div>

                        <div id="btn-extra">
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
